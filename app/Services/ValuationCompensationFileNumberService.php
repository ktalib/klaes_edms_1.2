<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ValuationCompensationFileNumberService
{
    /**
     * Generate the next file number for Valuation for Compensation.
     * Format: COM-PRJ-YEAR-SERIAL NO (e.g., COM-PRJ-2024-0001)
     *
     * @return string
     */
    public function generateNextFileNumber()
    {
        $year = Carbon::now()->year;
        
        return DB::connection('sqlsrv')->transaction(function () use ($year) {
            $sequence = DB::connection('sqlsrv')->table('valuation_comp_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                DB::connection('sqlsrv')->table('valuation_comp_sequences')->insert([
                    'year' => $year,
                    'last_serial' => 1,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
                $serial = 1;
            } else {
                $serial = $sequence->last_serial + 1;
                DB::connection('sqlsrv')->table('valuation_comp_sequences')
                    ->where('id', $sequence->id)
                    ->update([
                        'last_serial' => $serial,
                        'updated_at' => Carbon::now(),
                    ]);
            }
            
            $serial = sprintf('%04d', $serial);
            $fileNumber = "COM-PRJ-{$year}-{$serial}";

            // Final check against the main table to be absolutely sure
            $exists = DB::connection('sqlsrv')->table('valuation_compensations')
                ->where('our_ref', $fileNumber)
                ->exists();

            if ($exists) {
                // If by some miracle it exists (e.g. manual entry previously),
                // we update the sequence to jump over it and try again
                DB::connection('sqlsrv')->table('valuation_comp_sequences')
                    ->where('id', $sequence->id)
                    ->update([
                        'last_serial' => intval($serial),
                        'updated_at' => Carbon::now(),
                    ]);
                
                // Recursive call to get the next one
                return $this->generateNextFileNumber();
            }

            return $fileNumber;
        });
    }
}
