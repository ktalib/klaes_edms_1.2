<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $permission = Permission::firstOrCreate(
            ['name' => 'view-activity-reports', 'guard_name' => 'web']
        );

        $superAdminRole = Role::where('name', 'super admin')->first();
        if ($superAdminRole && !$superAdminRole->hasPermissionTo($permission)) {
            $superAdminRole->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $permission = Permission::where('name', 'view-activity-reports')->first();
        if ($permission) {
            $permission->delete();
        }
    }
};
