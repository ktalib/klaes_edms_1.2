<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Removes unused columns (status, notes, prepared_by) and makes
     * essential columns NOT NULL to match simplified form requirements.
     */
    public function up(): void
    {
        $connection = 'sqlsrv';
        $table = 'deeds_bill_balances_metadata';

        // Update NULL values before constraint changes
        DB::connection($connection)->table($table)
            ->whereNull('applicant_name')
            ->update(['applicant_name' => 'UNKNOWN']);

        DB::connection($connection)->table($table)
            ->whereNull('location_station')
            ->update(['location_station' => 'UNKNOWN']);

        DB::connection($connection)->table($table)
            ->whereNull('district')
            ->update(['district' => 'UNKNOWN']);

        DB::connection($connection)->table($table)
            ->whereNull('amount')
            ->update(['amount' => 0.00]);

        Schema::connection($connection)->table($table, function (Blueprint $table) {
            // Drop unused columns
            $table->dropColumn(['status', 'notes', 'prepared_by']);

            // Make required columns NOT NULL
            $table->string('applicant_name', 120)->nullable(false)->change();
            $table->string('location_station', 120)->nullable(false)->change();
            $table->string('district', 120)->nullable(false)->change();
            $table->decimal('amount', 15, 2)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = 'sqlsrv';
        $table = 'deeds_bill_balances_metadata';

        Schema::connection($connection)->table($table, function (Blueprint $table) {
            // Restore dropped columns
            $table->string('status', 50)->nullable()->after('amount');
            $table->text('notes')->nullable()->after('district');
            $table->string('prepared_by', 120)->nullable()->after('applicant_address');

            // Revert NOT NULL constraints
            $table->string('applicant_name', 120)->nullable()->change();
            $table->string('location_station', 120)->nullable()->change();
            $table->string('district', 120)->nullable()->change();
            $table->decimal('amount', 15, 2)->nullable()->change();
        });
    }
};
