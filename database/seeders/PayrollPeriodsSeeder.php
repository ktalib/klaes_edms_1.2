<?php

namespace Database\Seeders;

use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PayrollPeriodsSeeder extends Seeder
{
    public function run(): void
    {
        $actorId = User::query()
            ->whereRaw('LOWER(type) = ?', ['super admin'])
            ->value('id');

        $periods = $this->generatePeriods();

        foreach ($periods as $period) {
            PayrollPeriod::firstOrCreate(
                ['period_code' => $period['period_code']],
                [
                    'start_date' => $period['start_date'],
                    'end_date' => $period['end_date'],
                    'status' => 'draft',
                    'created_by' => $actorId,
                ]
            );
        }

        $this->command->info('Seeded ' . count($periods) . ' payroll periods.');
    }

    protected function generatePeriods(): array
    {
        $periods = [];
        $now = Carbon::now();
        
        // Generate periods for last 12 months and next 3 months
        for ($i = -12; $i <= 3; $i++) {
            $monthStart = $now->copy()->addMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            
            $periods[] = [
                'period_code' => $monthStart->format('Y-m'),
                'start_date' => $monthStart->toDateString(),
                'end_date' => $monthEnd->toDateString(),
            ];
        }

        return $periods;
    }
}
