<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs Subdivision runs of the Manual File Linkage screen that half-committed.
 *
 * The linkage writes six things per child plot: a file_indexings row, a fileNumber
 * row, a PropID_Master allocation, a PRA "Plot Subdivision" transaction, a
 * manual_file_linkages audit row and (once) the mother's decommission. When the
 * file_indexings insert failed — created_by is NOT NULL with no default, and the
 * controller used to omit it — the child was left with everything EXCEPT its
 * indexing row, so Legal Search could not see the subdivision at all.
 *
 * The same runs also lost the mother→child lineage: parent_prop_id came back null
 * and was written as null onto the child's indexing, fileNumber and PRA rows.
 *
 * This command re-derives both from the surviving manual_file_linkages audit rows.
 * Dry-run unless --force.
 */
class BackfillSubdivisionLinkageChildren extends Command
{
    protected $signature = 'linkage:backfill-subdivision-children
                            {--file=* : Only these child file numbers}
                            {--group=* : Only these linkage_group_id values}
                            {--since= : Only linkages created on/after this date (Y-m-d)}
                            {--skip-parent-propid : Only create missing indexing/PRA rows; leave parent_prop_id alone}
                            {--force : Apply the changes (default is a dry run)}';

    protected $description = 'Backfill missing file_indexings / PRA rows and mother→child parent_prop_id for Manual Linkage subdivision children';

    private const ACTOR = 'System (Subdivision Linkage Backfill)';

    public function handle(): int
    {
        $conn   = DB::connection('sqlsrv');
        $apply  = (bool) $this->option('force');
        $files  = array_filter(array_map('strtoupper', (array) $this->option('file')));
        $groups = array_filter((array) $this->option('group'));

        $query = $conn->table('manual_file_linkages')->where('workflow_type', 'Subdivision');
        if ($files) {
            $query->whereIn('new_file_number', $files);
        }
        if ($groups) {
            $query->whereIn('linkage_group_id', $groups);
        }
        if ($since = $this->option('since')) {
            $query->whereDate('created_at', '>=', $since);
        }

        $linkages = $query->orderBy('id')->get();

        if ($linkages->isEmpty()) {
            $this->warn('No Subdivision linkage rows matched the filters.');
            return self::SUCCESS;
        }

        $this->info(($apply ? 'APPLY' : 'DRY RUN') . ' — ' . $linkages->count() . ' subdivision child linkage row(s)');
        $this->line('');

        $stats = ['indexing_created' => 0, 'pra_created' => 0, 'parent_linked' => 0, 'ok' => 0, 'skipped' => 0];

        foreach ($linkages as $link) {
            $child  = strtoupper(trim((string) $link->new_file_number));
            $mother = $this->motherFileNumber($link);

            if ($child === '') {
                continue;
            }

            $parentPropId = $this->option('skip-parent-propid') ? null : $this->resolvePropId($conn, $mother);
            $actions      = [];

            $indexing = $conn->table('file_indexings')->where('file_number', $child)->first();
            $motherRow = $this->motherRecord($conn, $mother);

            // --- 1. missing file_indexings row ------------------------------
            if (!$indexing) {
                if (!$motherRow) {
                    $this->line("  <fg=yellow>SKIP</> {$child} — no indexing row and mother {$mother} not found in file_indexings/deprecated_records");
                    $stats['skipped']++;
                    continue;
                }

                $payload = $this->indexingPayload($child, $link, $motherRow, $mother, $parentPropId);
                $actions[] = 'create file_indexings (prop_id ' . ($payload['prop_id'] ?? 'null')
                    . ', parent_prop_id ' . ($parentPropId ?? 'null') . ')';

                if ($apply) {
                    $payload['created_at'] = now();
                    $newId = $conn->table('file_indexings')->insertGetId($payload);
                    $indexing = $conn->table('file_indexings')->where('id', $newId)->first();
                }
                $stats['indexing_created']++;
            } elseif ($parentPropId !== null && empty($indexing->parent_prop_id)) {
                $actions[] = "file_indexings.parent_prop_id → {$parentPropId}";
                if ($apply) {
                    $conn->table('file_indexings')->where('id', $indexing->id)->update([
                        'parent_prop_id' => $parentPropId,
                        'updated_by'     => self::ACTOR,
                        'updated_at'     => now(),
                    ]);
                }
                $stats['parent_linked']++;
            }

            // --- 2. fileNumber lineage --------------------------------------
            if ($parentPropId !== null && Schema::connection('sqlsrv')->hasColumn('fileNumber', 'parent_prop_id')) {
                $fnRow = $conn->table('fileNumber')->where('mlsfNo', $child)->first(['id', 'parent_prop_id']);
                if ($fnRow && empty($fnRow->parent_prop_id)) {
                    $actions[] = "fileNumber.parent_prop_id → {$parentPropId}";
                    if ($apply) {
                        $conn->table('fileNumber')->where('id', $fnRow->id)
                            ->update(['parent_prop_id' => $parentPropId, 'updated_at' => now()]);
                    }
                }
            }

            // --- 3. PRA "Plot Subdivision" transaction ----------------------
            $pra = $conn->table('pra')
                ->where('mlsFNo', $child)
                ->where('transaction_type', 'Plot Subdivision')
                ->first(['id', 'parent_prop_id']);

            if (!$pra) {
                $praPayload = $this->praPayload($child, $link, $motherRow, $mother, $parentPropId, $conn);
                $actions[]  = 'create pra "Plot Subdivision"';
                if ($apply) {
                    $conn->table('pra')->insert($praPayload);
                }
                $stats['pra_created']++;
            } elseif ($parentPropId !== null && empty($pra->parent_prop_id)) {
                $actions[] = "pra.parent_prop_id → {$parentPropId}";
                if ($apply) {
                    $conn->table('pra')->where('id', $pra->id)
                        ->update(['parent_prop_id' => $parentPropId, 'updated_at' => now()]);
                }
                $stats['parent_linked']++;
            }

            if (empty($actions)) {
                $stats['ok']++;
                continue;
            }

            $this->line("  <fg=cyan>{$child}</> (from {$mother}) — " . implode('; ', $actions));
        }

        $this->line('');
        $this->info(sprintf(
            'file_indexings created: %d | PRA rows created: %d | parent_prop_id links set: %d | already correct: %d | skipped: %d',
            $stats['indexing_created'],
            $stats['pra_created'],
            $stats['parent_linked'],
            $stats['ok'],
            $stats['skipped']
        ));

        if (!$apply) {
            $this->warn('DRY RUN — nothing was written. Re-run with --force to apply.');
        }

        return self::SUCCESS;
    }

    /** First (mother) file number recorded on the linkage audit row. */
    private function motherFileNumber(object $link): string
    {
        $old = json_decode((string) $link->old_file_numbers, true);
        if (is_array($old) && !empty($old)) {
            return strtoupper(trim((string) $old[0]));
        }

        return strtoupper(trim((string) $link->old_file_numbers));
    }

    /**
     * The mother's record: still active in file_indexings, or archived into
     * deprecated_records by the subdivision's own decommission step.
     */
    private function motherRecord($conn, string $mother): ?object
    {
        if ($mother === '') {
            return null;
        }

        return $conn->table('file_indexings')->where('file_number', $mother)->first()
            ?: $conn->table('deprecated_records')->where('file_number', $mother)
                ->orderByDesc('id')->first();
    }

    /**
     * The mother's prop_id. Its indexing row is often blank (that is exactly why the
     * linkage lost the lineage), so fall back to PropID_Master, which is the
     * authority on which parcel a file number stands for.
     */
    private function resolvePropId($conn, string $mother): ?int
    {
        if ($mother === '') {
            return null;
        }

        $row = $this->motherRecord($conn, $mother);
        if ($row && !empty($row->prop_id)) {
            return (int) $row->prop_id;
        }

        $master = $conn->table('PropID_Master')
            ->where('primary_file_number', $mother)
            ->orWhere('mlsFNo', $mother)
            ->orderBy('id')
            ->first(['prop_id']);

        return $master ? (int) $master->prop_id : null;
    }

    /** Strip the stray JSON quoting some holder columns were captured with. */
    private function clean(?string $value): ?string
    {
        $value = trim((string) $value, " \t\n\r\0\x0B\"");

        return $value === '' ? null : $value;
    }

    private function indexingPayload(string $child, object $link, object $mother, string $motherNo, ?int $parentPropId): array
    {
        $title = $this->clean($link->applicant_name) ?: $this->clean($mother->file_title ?? null);

        return [
            'file_number'      => $child,
            'file_title'       => $title ?: 'Manual Linkage Result',
            'land_use_type'    => $mother->land_use_type ?? 'N/A',
            'plot_number'      => $link->child_plot_number ?: ($mother->plot_number ?? 'N/A'),
            'district'         => $mother->district ?? null,
            'lga'              => $mother->lga ?? null,
            'location'         => $mother->location ?? null,
            'plot_size'        => $link->child_plot_size ?: ($mother->plot_size ?? null),
            'tp_no'            => $mother->tp_no ?? null,
            'lpkn_no'          => $mother->lpkn_no ?? null,
            'tracking_id'      => $mother->tracking_id ?? null,
            'original_holder'  => $this->clean($mother->original_holder ?? null) ?: $title,
            'current_holder'   => $title,
            'parent_prop_id'   => $parentPropId,
            'related_fileno'   => json_encode([$motherNo]),
            'has_transaction'  => 1,
            'prop_id'          => $link->prop_id ?: null,
            'general_registry' => $mother->general_registry ?? 'MLS',
            'registry'         => $mother->registry ?? null,
            'workflow_status'  => 'indexed',
            'created_by'       => self::ACTOR,
            'updated_by'       => self::ACTOR,
            'updated_at'       => now(),
        ];
    }

    private function praPayload(string $child, object $link, ?object $mother, string $motherNo, ?int $parentPropId, $conn): array
    {
        $siblings = $conn->table('manual_file_linkages')
            ->where('workflow_type', 'Subdivision')
            ->where('linkage_group_id', $link->linkage_group_id)
            ->count() ?: 1;

        $location = $mother->location ?? null;
        $grantee  = $this->clean($link->applicant_name);

        return [
            'prop_id'              => $link->prop_id ?: null,
            'mlsFNo'               => $child,
            'title_type'           => 'Subdivision',
            'transaction_type'     => 'Plot Subdivision',
            'property_description' => $location,
            'location'             => $location,
            'temp_fileno'          => $link->holding_file_no ?? null,
            'Grantor'              => $this->clean($mother->current_holder ?? null) ?: $this->clean($mother->file_title ?? null),
            'Grantee'              => $grantee,
            'party_1'              => $this->clean($mother->current_holder ?? null) ?: $this->clean($mother->file_title ?? null),
            'party_2'              => $grantee,
            'parent_prop_id'       => $parentPropId,
            'comments'             => "Plot Subdivision: {$siblings} Plots Subdivided from {$motherNo}",
            // Back-captured legacy transaction — the real instrument date is unknown,
            // and stamping today would date a years-old subdivision to the repair run.
            'transaction_date'     => null,
            'created_at'           => $link->created_at ?? now(),
            'updated_at'           => now(),
        ];
    }
}
