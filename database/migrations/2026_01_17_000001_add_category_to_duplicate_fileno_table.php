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
            if (!Schema::hasColumn('duplicate_fileno', 'category')) {
                $table->string('category', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('duplicate_fileno', function (Blueprint $table) {
            if (Schema::hasColumn('duplicate_fileno', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
/*
-- SQL Server (up)
IF COL_LENGTH('duplicate_fileno', 'category') IS NULL
    ALTER TABLE duplicate_fileno ADD category NVARCHAR(100) NULL;

-- SQL Server (down)
IF COL_LENGTH('duplicate_fileno', 'category') IS NOT NULL
    ALTER TABLE duplicate_fileno DROP COLUMN category;
*/