<?php

namespace App\Services;

use App\Models\MlsFileNo;
use App\Models\MlsSerialControl;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Allocation of MLS file-number serials (mls_serial_control) with gap skipping.
 *
 * Lifted out of MlsFileNoController so the ST commissioning flow can draw a
 * CON-* serial from the very same stream a land conversion uses -- an ST
 * conversion and a land conversion must never be handed the same number.
 */
class MlsSerialAllocationService
{
    /**
     * Take the next serial for a land-use prefix, skipping serials whose file
     * number is already in use anywhere.
     *
     * @return array{serial:int,file_number:string,skipped:array<int,array{serial:int,file_number:string}>}
     */
    public function allocateNextFreeSerial(string $landUse, int $year, string $suffix = '', int $maxTries = 200): array
    {
        $skipped = [];
        $tries = 0;
        $serial = null;
        $candidate = null;

        do {
            $serial = MlsSerialControl::getNextSerial($landUse, $year);
            $candidate = MlsFileNo::generateFileNumber($landUse, $year, $serial) . $suffix;

            $taken = MlsFileNo::where('full_file_number', $candidate)->exists()
                || DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $candidate)->exists()
                || count($this->takenInFileIndexings([$candidate])) > 0;

            if ($taken) {
                $skipped[] = ['serial' => $serial, 'file_number' => $candidate];
            }
            $tries++;
        } while ($taken && $tries < $maxTries);

        if ($taken) {
            throw new RuntimeException("Could not find a free file number for {$landUse}-{$year} after {$maxTries} attempts. Too many consecutive serials are already in use.");
        }

        return ['serial' => $serial, 'file_number' => $candidate, 'skipped' => $skipped];
    }

    /**
     * Which of these candidate file numbers already exist in file_indexings?
     *
     * Legacy imported rows carry a trailing CRLF in file_number (e.g. "IND-2026-213\r\n"),
     * which an exact `where` never matches -- the serial then looks free, gets handed out,
     * and no skip is reported even though the number is in use. Comparing on the
     * whitespace-stripped value catches both the clean and the dirty rows.
     *
     * Returns a set keyed by the *candidate* string (not the stored value) so callers can
     * look up by the number they asked about.
     */
    public function takenInFileIndexings(array $candidates): array
    {
        $candidates = array_values(array_unique(array_filter($candidates, 'strlen')));
        if (empty($candidates)) {
            return [];
        }

        $normalized = "LTRIM(RTRIM(REPLACE(REPLACE(file_number, CHAR(13), ''), CHAR(10), '')))";
        $placeholders = implode(',', array_fill(0, count($candidates), '?'));

        $found = DB::connection('sqlsrv')->table('file_indexings')
            ->whereRaw("{$normalized} IN ({$placeholders})", $candidates)
            ->selectRaw("{$normalized} AS normalized_file_number")
            ->pluck('normalized_file_number')
            ->all();

        return array_flip($found);
    }
}
