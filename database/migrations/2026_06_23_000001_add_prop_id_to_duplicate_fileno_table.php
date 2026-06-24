<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::table('duplicate_fileno', function (Blueprint $table) {
            if (!Schema::hasColumn('duplicate_fileno', 'prop_id')) {
                $table->integer('prop_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('duplicate_fileno', function (Blueprint $table) {
            if (Schema::hasColumn('duplicate_fileno', 'prop_id')) {
                $table->dropColumn('prop_id');
            }
        });
    }
};
/*
-- SQL Server (up)
IF COL_LENGTH('duplicate_fileno', 'prop_id') IS NULL
    ALTER TABLE duplicate_fileno ADD prop_id INT NULL;

-- SQL Server (down)
IF COL_LENGTH('duplicate_fileno', 'prop_id') IS NOT NULL
    ALTER TABLE duplicate_fileno DROP COLUMN prop_id;
*/
