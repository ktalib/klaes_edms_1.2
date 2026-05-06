<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('customers', function (Blueprint $table) {
            $table->string('reason_retired', 255)
                ->nullable()
                ->after('status')
                ->comment('Explains why a customer record was moved to retired status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->table('customers', function (Blueprint $table) {
            $table->dropColumn('reason_retired');
        });
    }
};
