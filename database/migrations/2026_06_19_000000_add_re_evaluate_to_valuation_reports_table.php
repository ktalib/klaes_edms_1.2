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
        Schema::connection('sqlsrv')->table('valuation_reports', function (Blueprint $table) {
            // Marks a printed row that has been superseded by a re-evaluation.
            $table->boolean('re_evaluate')->default(0);
            // Links a re-evaluated (new) row back to the original printed row.
            $table->unsignedBigInteger('parent_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->table('valuation_reports', function (Blueprint $table) {
            $table->dropColumn(['re_evaluate', 'parent_id']);
        });
    }
};
