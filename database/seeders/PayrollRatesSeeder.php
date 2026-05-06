<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Payroll\PayrollRate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PayrollRatesSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            ['code' => 'EMP001', 'first' => 'Ibrahim', 'last' => 'Mohammed', 'unit' => 'PRA', 'position' => 'Senior Officer', 'rate' => 5000, 'shift' => 8],
            ['code' => 'EMP002', 'first' => 'Fatima', 'last' => 'Abubakar', 'unit' => 'PIC', 'position' => 'Officer', 'rate' => 4500, 'shift' => 8],
            ['code' => 'EMP003', 'first' => 'Usman', 'last' => 'Abdullahi', 'unit' => 'Page Typing', 'position' => 'Lead Typist', 'rate' => 6000, 'shift' => 4],
            ['code' => 'EMP004', 'first' => 'Aisha', 'last' => 'Bello', 'unit' => 'Blind Scanning', 'position' => 'Scanner Operator', 'rate' => 4000, 'shift' => 4],
            ['code' => 'EMP005', 'first' => 'Yusuf', 'last' => 'Garba', 'unit' => 'Indexing', 'position' => 'Indexing Specialist', 'rate' => 7000, 'shift' => 8],
            ['code' => 'EMP006', 'first' => 'Halima', 'last' => 'Aliyu', 'unit' => 'File Archiving', 'position' => 'Archive Manager', 'rate' => 5500, 'shift' => 4],
            ['code' => 'EMP007', 'first' => 'Musa', 'last' => 'Ibrahim', 'unit' => 'File Tracking', 'position' => 'Tracking Officer', 'rate' => 4800, 'shift' => 8],
            ['code' => 'EMP008', 'first' => 'Zainab', 'last' => 'Hassan', 'unit' => 'Sorting, Collation & Batching', 'position' => 'Operations Lead', 'rate' => 5200, 'shift' => 4],
        ];

        $effectiveDate = Carbon::now()->startOfMonth();
        $expiresAt = null;
        $actorId = User::query()
            ->whereRaw('LOWER(type) = ?', ['super admin'])
            ->value('id');

        foreach ($entries as $entry) {
            DB::connection('sqlsrv')->transaction(function () use ($entry, $effectiveDate, $expiresAt, $actorId) {
                $department = Department::firstOrCreate(
                    ['name' => $entry['unit']],
                    [
                        'code' => Str::upper(Str::slug($entry['unit'])) ?: null,
                        'description' => $entry['unit'],
                        'is_active' => true,
                    ]
                );

                $user = User::query()
                    ->whereRaw('LOWER(first_name) = ? AND LOWER(last_name) = ?', [
                        mb_strtolower($entry['first']),
                        mb_strtolower($entry['last']),
                    ])
                    ->first();

                if (!$user) {
                    $email = Str::slug($entry['first'] . '.' . $entry['last']) . '@payroll.local';
                    $username = Str::slug($entry['first'] . '.' . $entry['last']);
                    if (User::query()->where('username', $username)->exists()) {
                        $username .= '-' . Str::lower(Str::random(4));
                    }

                    $user = User::create([
                        'first_name' => $entry['first'],
                        'last_name' => $entry['last'],
                        'email' => $email,
                        'password' => Hash::make('Password!23'),
                        'type' => 'staff',
                        'is_active' => 1,
                        'assign_role' => 'Payroll Management',
                        'department_id' => $department->id,
                        'user_level' => $entry['position'],
                        'user_type' => 'employee',
                        'username' => $username,
                        'work_days_per_week' => 5,
                        'man_hours_per_day' => $entry['shift'],
                        'staff_type_category' => 'MDC',
                        'work_station' => $entry['unit'],
                    ]);
                } else {
                    $user->department_id ??= $department->id;
                    $user->user_level ??= $entry['position'];
                    $user->man_hours_per_day ??= $entry['shift'];
                    $user->work_station ??= $entry['unit'];
                    if ($user->assign_role) {
                        $roles = collect(explode(',', $user->assign_role))
                            ->map(fn ($role) => trim($role))
                            ->filter();
                        if (!$roles->contains(fn ($role) => mb_strtolower($role) === 'payroll management')) {
                            $roles->push('Payroll Management');
                            $user->assign_role = $roles->implode(',');
                        }
                    } else {
                        $user->assign_role = 'Payroll Management';
                    }
                    $user->save();
                }

                PayrollRate::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'effective_date' => $effectiveDate->toDateString(),
                    ],
                    [
                        'daily_rate' => $entry['rate'],
                        'shift_hours' => $entry['shift'],
                        'expires_at' => $expiresAt,
                        'is_active' => true,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]
                );
            });
        }
    }
}
