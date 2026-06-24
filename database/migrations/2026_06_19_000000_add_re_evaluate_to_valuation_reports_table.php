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
            // Re-evaluation values. When present they supersede the original
            // value_words / value_figures when printing the report.
            $table->text('re_value_words')->nullable();
            $table->string('re_value_figures')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->table('valuation_reports', function (Blueprint $table) {
            $table->dropColumn(['re_value_words', 're_value_figures']);
        });
    }
};
