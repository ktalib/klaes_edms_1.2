<?php

namespace App\Console\Commands;

use App\Services\InstrumentRegistrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Repair file titles that a "(T)"-filed instrument failed to propagate.
 *
 * InstrumentRegistrationService::syncPartyNames() used to match the instrument's literal
 * file number against file_indexings.file_number / mls_file_no. An instrument registered
 * against a temporary "(T)" number never matched (the "(T)" lives in temp_file_no, while
 * the core records are keyed to the BASE number), so all four core tables silently kept
 * the previous owner's name. The service now matches base/"(T)" variants; this command
 * repairs the records written before that fix.
 *
 * Safe by design:
 *   - Default is a DRY-RUN (read-only). Writes only with --apply.
 *   - Only considers instruments whose vault is Deed of Assignment or Power of Attorney —
 *     exactly the set syncPartyNames() propagates. C of O rows etc. are left alone.
 *   - Applies only the LATEST such instrument per file, so a file with several "(T)"
 *     deeds lands on its current owner rather than an intermediate one.
 *   - Skips files whose title already matches the target.
 */
class BackfillTempInstrumentTitles extends Command
{
    protected $signature = 'title:backfill-temp-instruments
        {--apply : Actually write changes. Omit for a read-only dry-run report.}
        {--file= : Restrict to a single file number (base or "(T)" form).}
        {--limit=0 : Optionally cap the number of files processed (0 = all).}';

    protected $description = 'Propagate file titles from instruments registered under a temporary "(T)" number, which the old syncPartyNames() silently skipped.';

    public function handle(InstrumentRegistrationService $regService): int
    {
        $apply = (bool) $this->option('apply');
        $limit = (int) $this->option('limit');
        $only = trim((string) $this->option('file'));
        $conn = DB::connection('sqlsrv');

        $this->info(($apply ? '[APPLY] ' : '[DRY-RUN] ') . 'Backfilling titles from "(T)"-filed instruments…');

        // Every instrument filed under a "(T)" number, newest first, so the first row seen
        // for a file is the one that determines the current owner.
        $query = $conn->table('deed_registrations')
            ->whereRaw("LTRIM(RTRIM(CAST(fileno AS NVARCHAR(100)))) LIKE '%(T)'")
            ->orderByRaw('TRY_CONVERT(DATE, deeds_date) DESC')
            ->orderByDesc('id');

        if ($only !== '') {
            $base = trim((string) preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $only));
            $query->whereRaw('UPPER(LTRIM(RTRIM(CAST(fileno AS NVARCHAR(100))))) = UPPER(?)', [$base . '(T)']);
        }

        $rows = $query->get(['id', 'fileno', 'instrument_type', 'grantee', 'deeds_date']);
        $this->line('Instruments filed under a "(T)" number: ' . $rows->count());

        $seen = [];
        $planned = [];
        $skippedType = 0;

        foreach ($rows as $row) {
            $fileNo = trim((string) $row->fileno);
            $grantee = trim((string) $row->grantee);
            $key = strtoupper($fileNo);

            if ($fileNo === '' || $grantee === '' || isset($seen[$key])) {
                continue;
            }

            // Mirror syncPartyNames()'s own gate so we never propagate a name it would not.
            if (!$this->propagatesTitle($regService, (string) $row->instrument_type)) {
                $skippedType++;
                continue;
            }

            $seen[$key] = true;

            $base = trim((string) preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $fileNo));
            $indexing = $conn->table('file_indexings')
                ->where(function ($q) use ($base, $fileNo) {
                    $q->whereIn('file_number', [$base, $fileNo])
                        ->orWhereIn('mls_file_no', [$base, $fileNo])
                        ->orWhereIn('temp_file_no', [$base, $fileNo]);
                })
                ->first(['file_number', 'file_title']);

            if (!$indexing) {
                $this->warn(sprintf('  SKIP  %-22s no file_indexings record (deed #%s)', $fileNo, $row->id));
                continue;
            }

            $current = trim((string) ($indexing->file_title ?? ''));
            if (strcasecmp($current, $grantee) === 0) {
                continue; // already correct
            }

            $planned[] = [
                'deed_id' => $row->id,
                'file_no' => $fileNo,
                'base' => $indexing->file_number,
                'instrument' => (string) $row->instrument_type,
                'from' => $current,
                'to' => $grantee,
            ];

            if ($limit > 0 && count($planned) >= $limit) {
                break;
            }
        }

        if ($skippedType > 0) {
            $this->line("Instruments skipped (type does not propagate a title): {$skippedType}");
        }

        if (empty($planned)) {
            $this->info('Nothing to backfill — every "(T)"-filed instrument already matches its file title.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Deed', 'Filed under', 'Core file', 'Instrument', 'Title now', 'Title should be'],
            array_map(fn ($p) => [
                $p['deed_id'], $p['file_no'], $p['base'], $p['instrument'], $p['from'] ?: '(blank)', $p['to'],
            ], $planned)
        );

        if (!$apply) {
            $this->newLine();
            $this->info('Dry run — no changes written. Re-run with --apply to update these ' . count($planned) . ' file(s).');
            return self::SUCCESS;
        }

        $updated = 0;
        $failed = 0;
        foreach ($planned as $p) {
            try {
                $result = $regService->syncPartyNames($p['file_no'], $p['instrument'], $p['to']);
                if (!empty($result['synced'])) {
                    $updated++;
                    $this->line(sprintf('  OK    %-22s %s -> %s', $p['file_no'], $p['from'] ?: '(blank)', $p['to']));
                } else {
                    $failed++;
                    $this->warn(sprintf('  MISS  %-22s syncPartyNames matched no rows', $p['file_no']));
                }
            } catch (Throwable $e) {
                $failed++;
                $this->error(sprintf('  FAIL  %-22s %s', $p['file_no'], $e->getMessage()));
            }
        }

        $this->newLine();
        $this->info("Updated: {$updated}   Failed/no-match: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Whether syncPartyNames() would propagate a title for this instrument type.
     * Uses the service's own vault mapping so the two can never drift apart.
     */
    private function propagatesTitle(InstrumentRegistrationService $regService, string $instrumentType): bool
    {
        try {
            $vault = $regService->resolveVaultName($instrumentType);
        } catch (Throwable $e) {
            return false;
        }

        return $vault === 'Deed of Assignment' || $vault === 'Power of Attorney';
    }
}
