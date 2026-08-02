<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrects the Grantor / party_1 on OSS "Transfer of Title (OP)" pra rows.
 *
 * These change-of-name Transfer of Title rows were batch-created with their
 * Grantor / party_1 stamped as the OP issuer "Kano State Government" instead of
 * the previous holder. The real original holder is the Grantee (party_2) of the
 * file's original Occupancy Permit (OP) allocation row.
 *
 * This command copies that OP allottee onto the Transfer of Title row, so the
 * "Original Holder" column reflects the true prior owner (e.g. GARZALI HAMZA)
 * rather than the government.
 *
 * Only rows that can be corrected from real data are touched:
 *  - The Transfer of Title row's Grantor must currently be either "Kano State
 *    Government" (case-insensitive) or blank/NULL — a row already carrying a real
 *    name is left alone. Blank Grantors come from a separate defect in the "Match OP"
 *    flow, which fell back with ?? and so let an empty-string Grantee through as the
 *    allottee; the recovery here is identical, so both states are handled together.
 *  - There must be an OP allocation row for the same file whose Grantee is a real,
 *    non-government name to copy from.
 *
 * Rows whose file has NO OP allocation row in pra are skipped and reported: their
 * original holder is not recorded anywhere and cannot be recovered here.
 *
 * Idempotent — re-running only touches rows still holding the government value.
 * Use --dry-run to preview.
 */
class FixOssOpTransferGrantor extends Command
{
    protected $signature = 'oss:fix-op-transfer-grantor
        {--dry-run : Show what would change without writing}
        {--blank-only : Only repair rows whose Grantor is blank/NULL, leaving the government-valued rows untouched}';

    protected $description = 'Fix Grantor/party_1 on OSS Transfer of Title (OP) pra rows using the OP allocation Grantee';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $blankOnly = (bool) $this->option('blank-only');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written.');
        }

        if ($blankOnly) {
            $this->info('Scope: blank/NULL Grantors only — government-valued rows are left untouched.');
        }

        $grantorFilter = $blankOnly
            ? "(t.Grantor IS NULL OR LTRIM(RTRIM(t.Grantor)) = '')"
            : "(UPPER(LTRIM(RTRIM(t.Grantor))) = 'KANO STATE GOVERNMENT'
                OR t.Grantor IS NULL OR LTRIM(RTRIM(t.Grantor)) = '')";

        $db = DB::connection('sqlsrv');

        // Every Transfer of Title (OP) row whose Grantor is still the government or is
        // blank, left-joined to the earliest OP allocation row's Grantee for the same file.
        $candidates = $db->select("
            WITH op AS (
                SELECT COALESCE(NULLIF(mlsFNo,''), fileno) AS f,
                       Grantee  AS op_grantee,
                       party_2  AS op_party_2,
                       ROW_NUMBER() OVER (
                           PARTITION BY COALESCE(NULLIF(mlsFNo,''), fileno)
                           ORDER BY id ASC
                       ) AS rn
                FROM pra
                WHERE (instrument_type LIKE '%Occupancy Permit%' OR transaction_type LIKE '%Occupancy Permit%')
                  AND (is_deleted IS NULL OR is_deleted = 0)
            )
            SELECT t.id,
                   COALESCE(NULLIF(t.mlsFNo,''), t.fileno) AS file_no,
                   ISNULL(t.Grantor, '') AS current_grantor,
                   NULLIF(LTRIM(RTRIM(op.op_grantee)), '') AS op_grantee,
                   NULLIF(LTRIM(RTRIM(op.op_party_2)), '') AS op_party_2
            FROM pra t
            LEFT JOIN op ON op.rn = 1
                        AND op.f = COALESCE(NULLIF(t.mlsFNo,''), t.fileno)
            WHERE (t.instrument_type LIKE '%Transfer of Title%' OR t.transaction_type LIKE '%Transfer of Title%')
              AND {$grantorFilter}
              AND (t.is_deleted IS NULL OR t.is_deleted = 0)
            ORDER BY file_no, t.id
        ");

        if (empty($candidates)) {
            $this->info('Nothing to fix — no Transfer of Title (OP) row still carries a government or blank Grantor.');
            return self::SUCCESS;
        }

        $fixed = 0;
        $skippedNoSource = 0;

        foreach ($candidates as $row) {
            // Prefer the OP row's Grantee; fall back to its party_2. Never accept a
            // blank or government value — that would leave the row no better off.
            $holder = $row->op_grantee ?: $row->op_party_2;
            $holder = trim((string) $holder);

            if ($holder === '' || stripos($holder, 'kano state government') !== false) {
                $skippedNoSource++;
                continue;
            }

            $from = trim((string) $row->current_grantor) !== '' ? "'{$row->current_grantor}'" : '(blank)';
            $this->line("  {$row->file_no}: {$from} -> '{$holder}'");

            if (! $dryRun) {
                $db->table('pra')
                    ->where('id', $row->id)
                    ->update([
                        'Grantor' => $holder,
                        'party_1' => $holder,
                    ]);
            }

            $fixed++;
        }

        $verb = $dryRun ? 'Would fix' : 'Fixed';
        $this->info("{$verb} {$fixed} Transfer of Title (OP) row(s).");

        if ($skippedNoSource) {
            $this->warn(
                "Skipped {$skippedNoSource} row(s) whose file has no OP allocation row to source the "
                . "original holder from — their prior owner is not recorded and cannot be recovered here."
            );
        }

        return self::SUCCESS;
    }
}
