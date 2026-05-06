<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create table for persistent timeline arrangements in Legal Search.
     */
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('legal_search_timeline_arrangements')) {
            Schema::connection('sqlsrv')->create('legal_search_timeline_arrangements', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('prop_id', 20);
                $table->string('source_table', 50);
                $table->unsignedBigInteger('source_id');
                $table->unsignedInteger('display_order');
                $table->unsignedBigInteger('arranged_by')->nullable();
                $table->timestamp('arranged_at')->nullable();
                $table->timestamps();

                $table->unique(['prop_id', 'source_table', 'source_id'], 'uq_ls_timeline_prop_source');
                $table->index(['prop_id', 'display_order'], 'idx_ls_timeline_prop_order');
            });
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('legal_search_timeline_arrangements')) {
            Schema::connection('sqlsrv')->drop('legal_search_timeline_arrangements');
        }
    }
};
