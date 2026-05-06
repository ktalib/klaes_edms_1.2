<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('users', 'passport_photo_path')) {
                $table->string('passport_photo_path', 255)->nullable()->after('profile');
            }
        });

        if (Schema::connection('sqlsrv')->hasColumn('users', 'passport_photo_path')) {
            DB::connection('sqlsrv')
                ->table('users')
                ->whereNotNull('profile')
                ->where('profile', '!=', '')
                ->update([
                    'passport_photo_path' => DB::raw("CASE WHEN profile LIKE 'upload/%' THEN profile ELSE CONCAT('upload/profile/', profile) END"),
                ]);
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('users', 'passport_photo_path')) {
                $table->dropColumn('passport_photo_path');
            }
        });
    }
};
