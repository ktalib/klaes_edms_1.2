<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The Online Legal Search portal no longer has user accounts. Each payment
     * is a self-contained guest transaction identified by a human-friendly
     * tracking id (e.g. USER-0001) so support/admin can trace it back-office.
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('legal_search_online_payments', function (Blueprint $table) {
            $table->string('tracking_id', 30)->nullable()->after('reference');
            $table->index('tracking_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->table('legal_search_online_payments', function (Blueprint $table) {
            $table->dropIndex(['tracking_id']);
            $table->dropColumn('tracking_id');
        });
    }
};
