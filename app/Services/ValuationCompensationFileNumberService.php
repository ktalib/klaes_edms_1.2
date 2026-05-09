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

            return $fileNumber;
        });
    }

    /**
     * Preview the next file number without incrementing the sequence.
     */
    public function peekNextFileNumber()
    {
        $year = Carbon::now()->year;
        $sequence = DB::connection('sqlsrv')->table('valuation_comp_sequences')
            ->where('year', $year)
            ->first();

        $serial = $sequence ? ($sequence->last_serial + 1) : 1;
        $serial = sprintf('%04d', $serial);
        
        return "COM-PRJ-{$year}-{$serial}";
    }
}
