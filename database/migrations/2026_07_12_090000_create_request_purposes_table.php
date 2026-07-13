<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lookup table for the "Request Purpose" a file is logged out for (e.g. Official,
 * Search, Correction). Separate from the existing App\Models\Purpose/`purposes`
 * table, which is an unrelated land-use/MLS concept.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('request_purposes')) {
            return;
        }

        Schema::connection('sqlsrv')->create('request_purposes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique();
            $table->unsignedInteger('turnaround_days')->default(5)->comment('Expected turnaround, in days, used to compute the file_tracker deadline');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('request_purposes');
    }
};
