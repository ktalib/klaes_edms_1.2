<?php
 /**
  * Dev: IDK
  * Date: 2024-06-17
  */
namespace App\Services;

use Illuminate\Support\Facades\DB;

class KangisFileNoPlaceholderService
{
    /**
     * Normalise a file number or placeholder to a canonical key used for
     * sibling detection. Strips leading zeros from the trailing serial so
     * that "MLKN 1", "MLKN 01", and "MLKN 001" all share the key "MLKN 1".
     */
    public function normalize(string $raw): string
    {
        $value = strtoupper(trim($raw));
        // Collapse separators
        $value = preg_replace('/[\s\-_]+/', ' ', $value);
        // Strip leading zeros from the trailing numeric segment
        $value = preg_replace_callback('/(\s)0*(\d+)$/', function ($m) {
            return $m[1] . ltrim($m[2], '0');
        }, $value);

        return trim($value);
    }

    /**
     * Resolve and persist the KANGIS placeholder for a newly created
     * file_indexings record.
     *
     * Sibling detection is based on the SELECTED FILE NUMBER (e.g. "MLKN 1"),
     * not the placeholder text. When two physical files ("MLKN 01" and
     * "MLKN 001") both resolve to the same Kangis awaiting file number
     * ("MLKN 1"), the system:
     *
     *  1. Stores the human-readable placeholder (e.g. "MLKN 01") as-is in
     *     `kangis_fileno_placeholder` on the new record.
     *  2. Overwrites `file_number` and `kangis_fileno_resolved` with
     *     "<selected_file_number>_1", "_2", "_3" … so each row is unique.
     *  3. Retroactively re-suffixes earlier siblings still on the bare
     *     file_number (first collision only — later ones already carry a suffix).
     *
     * Returns:
     *  [
     *    'resolved'             => string,  // e.g. "MLKN 1_2" — the new record's file_number
     *    'siblings'             => array,   // earlier records that were re-suffixed
     *    'selected_file_number' => string,  // the base key used
     *  ]
     */
    public function resolveAndSave(string $rawPlaceholder, int $newFileIndexingId): array
    {
        // The base key is the SELECTED file_number on the new record (e.g. "MLKN 1")
        $newRow = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('id', $newFileIndexingId)
            ->first(['id', 'file_number']);

        if (!$newRow || empty($newRow->file_number)) {
            return ['resolved' => $rawPlaceholder, 'siblings' => [], 'selected_file_number' => $rawPlaceholder];
        }

        $selectedFileNumber = trim((string) $newRow->file_number);
        $normalizedKey      = $this->normalize($selectedFileNumber);

        if ($normalizedKey === '') {
            return ['resolved' => $rawPlaceholder, 'siblings' => [], 'selected_file_number' => $selectedFileNumber];
        }

        // Check whether the bare file number already exists in another record.
        $bareRecord = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('id', '!=', $newFileIndexingId)
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$selectedFileNumber])
            ->first(['id', 'file_number']);

        $bareExists = $bareRecord !== null;

        // Count existing _N suffixed variants (e.g. KNML 12_1, KNML 12_2 …).
        $suffixedVariants = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('id', '!=', $newFileIndexingId)
            ->where('file_number', 'like', $selectedFileNumber . '_%')
            ->pluck('file_number')
            ->filter(function ($fn) use ($selectedFileNumber) {
                $suffix = substr(trim((string) $fn), strlen($selectedFileNumber) + 1);
                return ctype_digit($suffix) && $suffix !== '';
            });

        $suffixCount = $suffixedVariants->count();

        // ── Case A: no bare, no suffixed → very first record, keep bare (no suffix). ──
        if (!$bareExists && $suffixCount === 0) {
            DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $newFileIndexingId)
                ->update([
                    'kangis_fileno_placeholder' => $rawPlaceholder,
                    'kangis_fileno_resolved'    => $selectedFileNumber,
                ]);

            // Keep fileNumber mapping in sync even on first/no-collision save.
            $this->syncToFileNumber($selectedFileNumber, $rawPlaceholder, $selectedFileNumber);

            return [
                'resolved'             => $selectedFileNumber,
                'siblings'             => [],
                'selected_file_number' => $selectedFileNumber,
            ];
        }

        // ── Case B: bare exists, no suffixed yet → first collision. ──
        // Retroactively rename the bare record to _1, assign _2 to the new record.
        if ($bareExists && $suffixCount === 0) {
            $renamedBare = $selectedFileNumber . '_1';
            $newResolved = $selectedFileNumber . '_2';

            // Rename the bare record in file_indexings
            DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $bareRecord->id)
                ->update([
                    'file_number'            => $renamedBare,
                    'kangis_fileno_resolved' => $renamedBare,
                ]);

            // Cascade the rename of the bare record to related tables
            $this->syncFileNumberRename($selectedFileNumber, $renamedBare);
            $this->syncKangisGroupingRename($selectedFileNumber, $renamedBare);

            // Persist the new record with _2 suffix
            DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $newFileIndexingId)
                ->update([
                    'file_number'               => $newResolved,
                    'kangis_fileno_placeholder' => $rawPlaceholder,
                    'kangis_fileno_resolved'    => $newResolved,
                ]);

            $this->syncToFileNumber($selectedFileNumber, $rawPlaceholder, $newResolved);

            return [
                'resolved'             => $newResolved,
                'siblings'             => [['id' => $bareRecord->id, 'file_number' => $renamedBare]],
                'selected_file_number' => $selectedFileNumber,
            ];
        }

        // ── Cases C & D: suffixed variants already exist (bare may or may not be present). ──
        // The sequence is already in progress (_1 = retroactively renamed bare, _2, _3 …).
        // Simply append the next index.
        $nextIndex   = $suffixCount + 1;
        $newResolved = $selectedFileNumber . '_' . $nextIndex;

        DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('id', $newFileIndexingId)
            ->update([
                'file_number'               => $newResolved,
                'kangis_fileno_placeholder' => $rawPlaceholder,
                'kangis_fileno_resolved'    => $newResolved,
            ]);

        $this->syncToFileNumber($selectedFileNumber, $rawPlaceholder, $newResolved);

        return [
            'resolved'             => $newResolved,
            'siblings'             => [],
            'selected_file_number' => $selectedFileNumber,
        ];
    }

    /**
     * Rename a kangis_grouping row when a sibling's file_number gets suffixed.
     */
    private function syncKangisGroupingRename(string $oldFileNumber, string $newFileNumber): void
    {
        DB::connection('sqlsrv')
            ->table('kangis_grouping')
            ->whereRaw('UPPER(LTRIM(RTRIM(kangis_awaiting_fileno))) = UPPER(?)', [$oldFileNumber])
            ->update([
                'kangis_fileno'           => $newFileNumber,
                'indexing_kangis_fileno'  => $newFileNumber,
            ]);
    }

    /**
     * Cascade a file_number rename for the BARE sibling (retroactive _1 rename, Case B).
     * Updates every table that holds a reference to the old file number so the bare
     * record is fully reflected at its new _1 name before the new record is persisted.
     */
    private function syncFileNumberRename(string $oldFileNumber, string $newFileNumber): void
    {
        // fileNumber table — update all three possible columns that may hold the bare number
        DB::connection('sqlsrv')
            ->table('fileNumber')
            ->where(function ($q) use ($oldFileNumber) {
                $q->whereRaw('UPPER(LTRIM(RTRIM(kangisFileNo))) = UPPER(?)', [$oldFileNumber])
                  ->orWhereRaw('UPPER(LTRIM(RTRIM(mlsfNo))) = UPPER(?)', [$oldFileNumber])
                  ->orWhereRaw('UPPER(LTRIM(RTRIM(NewKANGISFileNo))) = UPPER(?)', [$oldFileNumber]);
            })
            ->update([
                'mlsfNo'                 => null,
                'kangisFileNo'           => $newFileNumber,
                'NewKANGISFileNo'        => $newFileNumber,
                'kangis_fileno_resolved' => $newFileNumber,
            ]);

        // file_history_staging
        DB::connection('sqlsrv')
            ->table('file_history_staging')
            ->whereRaw('UPPER(LTRIM(RTRIM(mlsFNo))) = UPPER(?)', [$oldFileNumber])
            ->update(['mlsFNo' => $newFileNumber]);

        DB::connection('sqlsrv')
            ->table('file_history_staging')
            ->whereRaw('UPPER(LTRIM(RTRIM(fileno))) = UPPER(?)', [$oldFileNumber])
            ->update(['fileno' => $newFileNumber]);

        DB::connection('sqlsrv')
            ->table('file_history_staging')
            ->whereRaw('UPPER(LTRIM(RTRIM(kangisFileNo))) = UPPER(?)', [$oldFileNumber])
            ->update(['kangisFileNo' => $newFileNumber]);

        // pra
        DB::connection('sqlsrv')
            ->table('pra')
            ->whereRaw('UPPER(LTRIM(RTRIM(mlsFNo))) = UPPER(?)', [$oldFileNumber])
            ->update(['mlsFNo' => $newFileNumber]);

        DB::connection('sqlsrv')
            ->table('pra')
            ->whereRaw('UPPER(LTRIM(RTRIM(fileno))) = UPPER(?)', [$oldFileNumber])
            ->update(['fileno' => $newFileNumber]);

        DB::connection('sqlsrv')
            ->table('pra')
            ->whereRaw('UPPER(LTRIM(RTRIM(kangisFileNo))) = UPPER(?)', [$oldFileNumber])
            ->update(['kangisFileNo' => $newFileNumber]);

        // entities_staging
        DB::connection('sqlsrv')
            ->table('entities_staging')
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$oldFileNumber])
            ->update(['file_number' => $newFileNumber]);

        // customers_staging
        DB::connection('sqlsrv')
            ->table('customers_staging')
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$oldFileNumber])
            ->update(['file_number' => $newFileNumber]);
    }

    /**
     * Mirror the new record's placeholder + resolved value onto the fileNumber table.
     * The fileNumber row still holds the original (unsuffixed) file_number at this point.
     */
    private function syncToFileNumber(string $originalFileNumber, string $placeholder, string $resolved): void
    {
        DB::connection('sqlsrv')
            ->table('fileNumber')
            ->where(function ($q) use ($originalFileNumber) {
                $q->where('kangisFileNo', $originalFileNumber)
                  ->orWhere('mlsfNo', $originalFileNumber)
                  ->orWhere('NewKANGISFileNo', $originalFileNumber);
            })
            ->update([
                'mlsfNo'                   => null,
                'kangisFileNo'              => $resolved,
                'NewKANGISFileNo'           => $resolved,
                'kangis_fileno_placeholder' => $placeholder,
                'kangis_fileno_resolved'    => $resolved,
            ]);
    }




     
}
