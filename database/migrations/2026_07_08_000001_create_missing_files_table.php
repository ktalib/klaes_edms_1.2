<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection($this->connection)->create('missing_files', function (Blueprint $table) {
            $table->id();

            // File being reported missing
            $table->string('file_number', 100);
            $table->string('file_title')->nullable();
            $table->string('tracking_id', 100)->nullable();

            // Where the file is supposed to live
            $table->string('archive_registry', 50)->nullable(); // Archive | Registry 1 | Registry 2 | Registry 3
            $table->string('rack_primary', 10)->nullable();      // e.g. A
            $table->string('rack_secondary', 10)->nullable();    // backup rack, e.g. B
            $table->string('shelf_number', 10)->nullable();      // e.g. 12
            $table->string('full_label', 30)->nullable();        // e.g. A12
            $table->string('shelf_location', 30)->nullable();    // normalized, e.g. A12

            // Who reported it and why
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->string('reported_by_name')->nullable();
            $table->text('remarks')->nullable();

            // Status: Missing | Searching | Found
            $table->string('status', 20)->default('Missing');

            // Resolution
            $table->timestamp('found_at')->nullable();
            $table->unsignedBigInteger('found_by')->nullable();
            $table->string('found_by_name')->nullable();

            $table->timestamps();

            $table->index('file_number');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('missing_files');
    }
};
