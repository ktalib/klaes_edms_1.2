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
            $table->string('use')->nullable();
            $table->string('property_desc')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->table('valuation_reports', function (Blueprint $table) {
            $table->dropColumn(['use', 'property_desc']);
        });
    }
};
