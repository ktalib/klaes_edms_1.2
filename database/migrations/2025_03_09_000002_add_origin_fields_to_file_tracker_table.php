<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The connection name for the migration.
     */
    protected $connection = 'sqlsrv';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->table('file_tracker', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('file_tracker', 'origin_office_code')) {
                $table->string('origin_office_code', 100)->nullable();
            }

            if (!Schema::connection($this->connection)->hasColumn('file_tracker', 'origin_office_name')) {
                $table->string('origin_office_name')->nullable();
            }

            if (!Schema::connection($this->connection)->hasColumn('file_tracker', 'origin_office_department')) {
                $table->string('origin_office_department')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('file_tracker', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn('file_tracker', 'origin_office_department')) {
                $table->dropColumn('origin_office_department');
            }

            if (Schema::connection($this->connection)->hasColumn('file_tracker', 'origin_office_name')) {
                $table->dropColumn('origin_office_name');
            }

            if (Schema::connection($this->connection)->hasColumn('file_tracker', 'origin_office_code')) {
                $table->dropColumn('origin_office_code');
            }
        });
    }
};
