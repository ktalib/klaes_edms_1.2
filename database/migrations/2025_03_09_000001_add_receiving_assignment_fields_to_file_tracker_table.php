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
            if (!Schema::connection($this->connection)->hasColumn('file_tracker', 'receiving_office_code')) {
                $table->string('receiving_office_code', 100)->nullable();
            }

            if (!Schema::connection($this->connection)->hasColumn('file_tracker', 'receiving_office_name')) {
                $table->string('receiving_office_name')->nullable();
            }

            if (!Schema::connection($this->connection)->hasColumn('file_tracker', 'receiving_officer_id')) {
                $table->bigInteger('receiving_officer_id')->nullable();
            }

            if (!Schema::connection($this->connection)->hasColumn('file_tracker', 'receiving_officer_name')) {
                $table->string('receiving_officer_name')->nullable();
            }

            if (!Schema::connection($this->connection)->hasColumn('file_tracker', 'assignment_status')) {
                $table->string('assignment_status', 50)->nullable()->default('PENDING');
            }

            if (!Schema::connection($this->connection)->hasColumn('file_tracker', 'assignment_accepted_at')) {
                $table->dateTime('assignment_accepted_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('file_tracker', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn('file_tracker', 'assignment_accepted_at')) {
                $table->dropColumn('assignment_accepted_at');
            }

            if (Schema::connection($this->connection)->hasColumn('file_tracker', 'assignment_status')) {
                $table->dropColumn('assignment_status');
            }

            if (Schema::connection($this->connection)->hasColumn('file_tracker', 'receiving_officer_name')) {
                $table->dropColumn('receiving_officer_name');
            }

            if (Schema::connection($this->connection)->hasColumn('file_tracker', 'receiving_officer_id')) {
                $table->dropColumn('receiving_officer_id');
            }

            if (Schema::connection($this->connection)->hasColumn('file_tracker', 'receiving_office_name')) {
                $table->dropColumn('receiving_office_name');
            }

            if (Schema::connection($this->connection)->hasColumn('file_tracker', 'receiving_office_code')) {
                $table->dropColumn('receiving_office_code');
            }
        });
    }
};
