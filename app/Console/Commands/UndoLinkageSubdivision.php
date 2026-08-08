<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reverses one Manual File Linkage *Subdivision* run, identified by its
 * linkage_group_id (every child of a single run shares one).
 *
 * Restores the mother from deprecated_records back into file_indexings and
 * fileNumber, then removes what the run created: the children's indexing /
 * fileNumber / PRA / PropID_Master rows, the linkage audit rows, and the
 * decommission records.
 *
 * Deliberately narrow: it only removes child rows that were created inside the
 * run's own time window, so a child file number that already existed before the
 * subdivision is left alone. Dry-run unless --force.
 */
class UndoLinkageSubdivision extends Command
{
    protected $signature = 'linkage:undo-subdivision
                            {group : linkage_group_id of the subdivision run}
                            {--force : Apply the rollback (default is a dry run)}';

    protected $description = 'Undo a Manual Linkage subdivision run: restore the mother file and delete the children it created';

    /** Rows created within this many seconds of the run are treated as its own. */
    private const WINDOW_SECONDS = 300;

    public function handle(): int
    {
        $conn  = DB::connection('sqlsrv');
        $apply = (bool) $this->option('force');
        $group = (string) $this->argument('group');

        $linkages = $conn->table('manual_file_linkages')
            ->where('workflow_type', 'Subdivision')
            ->where('linkage_group_id', $group)
            ->orderBy('id')
            ->get();

        if ($linkages->isEmpty()) {
            $this->error("No Subdivision linkage rows found for group {$group}.");
            return self::FAILURE;
        }

        $mother   = strtoupper(trim((string) (json_decode((string) $linkages->first()->old_file_numbers, true)[0] ?? '')));
        $children = $linkages->pluck('new_file_number')->map(fn ($f) => strtoupper(trim((string) $f)))->all();
        $propIds  = $linkages->pluck('prop_id')->filter()->map(fn ($p) => (int) $p)->all();

        $runStart = $linkages->min('created_at');
        $windowLo = \Carbon\Carbon::parse($runStart)->subSeconds(self::WINDOW_SECONDS);
        $windowHi = \Carbon\Carbon::parse($linkages->max('created_at'))->addSeconds(self::WINDOW_SECONDS);

        $this->info(($apply ? 'APPLY' : 'DRY RUN') . " — undo subdivision {$mother} → " . implode(', ', $children));
        $this->line('  linkage group : ' . $group);
        $this->line('  run window    : ' . $windowLo . ' .. ' . $windowHi);
        $this->line('');

        // --- The mother's archive row is the source for the restore -----------
        $dep = $conn->table('deprecated_records')
            ->where('file_number', $mother)
            ->orderByDesc('id')
            ->first();

        if (!$dep) {
            $this->error("No deprecated_records row for mother {$mother} — cannot restore it. Aborting.");
            return self::FAILURE;
        }
        if ($conn->table('file_indexings')->where('file_number', $mother)->exists()) {
            $this->error("{$mother} is already present in file_indexings — nothing to restore. Aborting.");
            return self::FAILURE;
        }

        $decomm = $conn->table('decommissioned_files')
            ->where(fn ($q) => $q->where('file_no', $mother)->orWhere('mls_file_no', $mother))
            ->orderByDesc('id')
            ->get(['id', 'file_number_id', 'decommissioning_date']);

        // Reuse the original ids when they are still free, so anything pointing at
        // them (links, trackers) lines back up instead of dangling.
        $origFiId = $dep->file_indexing_id
            && !$conn->table('file_indexings')->where('id', $dep->file_indexing_id)->exists()
                ? (int) $dep->file_indexing_id : null;

        $origFnId = optional($decomm->first())->file_number_id;
        $origFnId = $origFnId && !$conn->table('fileNumber')->where('id', $origFnId)->exists()
            ? (int) $origFnId : null;

        // --- Child rows this run created --------------------------------------
        $childIndexings = $conn->table('file_indexings')->whereIn('file_number', $children)
            ->whereBetween('created_at', [$windowLo, $windowHi])->pluck('id')->all();
        $childFileNos = $conn->table('fileNumber')->whereIn('mlsfNo', $children)
            ->whereBetween('created_at', [$windowLo, $windowHi])->pluck('id')->all();
        $childPra = $conn->table('pra')->whereIn('mlsFNo', $children)
            ->where('transaction_type', 'Plot Subdivision')
            ->whereBetween('created_at', [$windowLo, $windowHi])->pluck('id')->all();
        $childMasters = $conn->table('PropID_Master')->whereIn('prop_id', $propIds)
            ->whereIn('primary_file_number', $children)->pluck('id')->all();
        $childLinks = Schema::connection('sqlsrv')->hasTable('file_indexing_links')
            ? $conn->table('file_indexing_links')->whereIn('file_number', $children)->pluck('id')->all()
            : [];

        $this->line('  [RESTORE] file_indexings  ' . $mother . ($origFiId ? " (id {$origFiId})" : ' (new id)'));
        $this->line('  [RESTORE] fileNumber      ' . $mother . ($origFnId ? " (id {$origFnId})" : ' (new id)'));
        $this->line('  [DELETE]  file_indexings       ' . $this->fmt($childIndexings));
        $this->line('  [DELETE]  fileNumber           ' . $this->fmt($childFileNos));
        $this->line('  [DELETE]  pra                  ' . $this->fmt($childPra));
        $this->line('  [DELETE]  PropID_Master        ' . $this->fmt($childMasters));
        $this->line('  [DELETE]  file_indexing_links  ' . $this->fmt($childLinks));
        $this->line('  [DELETE]  manual_file_linkages ' . $this->fmt($linkages->pluck('id')->all()));
        $this->line('  [DELETE]  decommissioned_files ' . $this->fmt($decomm->pluck('id')->all()));
        $this->line('  [DELETE]  deprecated_records   ' . $this->fmt([$dep->id]));

        if (!$apply) {
            $this->line('');
            $this->warn('DRY RUN — nothing was written. Re-run with --force to apply.');
            return self::SUCCESS;
        }

        $conn->beginTransaction();
        try {
            $pdo = $conn->getPdo();

            // 1. Restore the mother's indexing row
            $fiPayload = [
                'file_number'       => $dep->file_number,
                'file_title'        => $dep->file_title,
                'land_use_type'     => $dep->land_use_type,
                'plot_number'       => $dep->plot_number,
                'district'          => $dep->district,
                'lga'               => $dep->lga,
                'location'          => $dep->location,
                'plot_size'         => $dep->plot_size ?: null,
                'tp_no'             => $dep->tp_no ?: null,
                'lpkn_no'           => $dep->lpkn_no ?: null,
                'tracking_id'       => $dep->tracking_id,
                'original_holder'   => $dep->original_holder,
                'current_holder'    => $dep->current_holder,
                'parent_prop_id'    => $dep->parent_prop_id ?: null,
                'related_fileno'    => $dep->related_fileno ?: null,
                'has_transaction'   => $dep->has_transaction,
                'workflow_status'   => $dep->workflow_status,
                'serial_no'         => $dep->serial_no ?: null,
                'batch_no'          => $dep->batch_no,
                'registry'          => $dep->registry,
                'general_registry'  => $dep->general_registry,
                'prop_id'           => $dep->prop_id ?: null,
                'phone'             => $dep->phone ?: null,
                'residence_address' => $dep->residence_address ?: null,
                'created_by'        => $dep->created_by,
                'updated_by'        => $dep->updated_by,
                'is_deleted'        => 0,
                'created_at'        => $dep->created_at ?: now(),
                'updated_at'        => now(),
            ];

            if ($origFiId) {
                $fiPayload['id'] = $origFiId;
                $pdo->exec('SET IDENTITY_INSERT [file_indexings] ON');
                $conn->table('file_indexings')->insert($fiPayload);
                $pdo->exec('SET IDENTITY_INSERT [file_indexings] OFF');
            } else {
                $conn->table('file_indexings')->insert($fiPayload);
            }
            $this->line('  [OK] restored file_indexings ' . $mother);

            // 2. Restore the mother's fileNumber row (skip if it never went away)
            if (!$conn->table('fileNumber')->where('mlsfNo', $mother)->exists()) {
                $fnPayload = [
                    'mlsfNo'            => $dep->file_number,
                    'FileName'          => $dep->file_title,
                    'tracking_id'       => $dep->tracking_id,
                    'location'          => $dep->location,
                    'lga'               => $dep->lga,
                    'plot_no'           => $dep->plot_number,
                    'tp_no'             => $dep->tp_no ?: null,
                    'created_by'        => $dep->created_by,
                    'updated_by'        => $dep->updated_by,
                    'is_decommissioned' => 0,
                    'is_deleted'        => 0,
                    'created_at'        => $dep->created_at ?: now(),
                    'updated_at'        => now(),
                ];
                if ($origFnId) {
                    $fnPayload['id'] = $origFnId;
                    $pdo->exec('SET IDENTITY_INSERT [fileNumber] ON');
                    $conn->table('fileNumber')->insert($fnPayload);
                    $pdo->exec('SET IDENTITY_INSERT [fileNumber] OFF');
                } else {
                    $conn->table('fileNumber')->insert($fnPayload);
                }
                $this->line('  [OK] restored fileNumber ' . $mother);
            }

            // 3. Remove what the run created
            $this->del($conn, 'file_indexings', $childIndexings);
            $this->del($conn, 'fileNumber', $childFileNos);
            $this->del($conn, 'pra', $childPra);
            $this->del($conn, 'PropID_Master', $childMasters);
            $this->del($conn, 'file_indexing_links', $childLinks);
            $this->del($conn, 'manual_file_linkages', $linkages->pluck('id')->all());
            $this->del($conn, 'decommissioned_files', $decomm->pluck('id')->all());
            $this->del($conn, 'deprecated_records', [$dep->id]);

            $conn->commit();
            $this->line('');
            $this->info('✓ Rollback complete.');
        } catch (\Throwable $e) {
            $conn->rollBack();
            try { $conn->getPdo()->exec('SET IDENTITY_INSERT [file_indexings] OFF'); } catch (\Throwable $_) {}
            try { $conn->getPdo()->exec('SET IDENTITY_INSERT [fileNumber] OFF'); } catch (\Throwable $_) {}
            $this->error('Rollback FAILED — transaction reverted: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function del($conn, string $table, array $ids): void
    {
        if (empty($ids)) {
            return;
        }
        $n = $conn->table($table)->whereIn('id', $ids)->delete();
        $this->line("  [OK] deleted {$n} row(s) from {$table}");
    }

    private function fmt(array $ids): string
    {
        return empty($ids) ? '(none)' : implode(', ', $ids);
    }
}
