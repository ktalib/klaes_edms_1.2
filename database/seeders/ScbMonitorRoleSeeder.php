<?php

namespace Database\Seeders;

use App\Models\UserRole;
use Illuminate\Database\Seeder;

/**
 * Seeds the SCB Monitor role — the mobile-only file searcher who receives and
 * actions File Requests (FR) from the Quick Search & File Location module.
 */
class ScbMonitorRoleSeeder extends Seeder
{
    public function run()
    {
        UserRole::updateOrCreate(
            ['name' => 'SCB Monitor'],
            [
                'guard_name'  => 'web',
                'description' => 'SCB Monitor / file searcher — mobile-only. Receives and actions File Requests (physical file searches).',
                'level'       => 1,
                'user_type'   => 'staff',
                'is_active'   => 1,
            ]
        );
    }
}
