<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the table if it exists
        Schema::connection('sqlsrv')->dropIfExists('file_indexing_links');

        // Create the table with specific fields allowed in the form
        Schema::connection('sqlsrv')->create('file_indexing_links', function (Blueprint $table) {
            $table->id();

            // Foreign Key to Main Record (Required for "Save at once" logic)
            $table->unsignedBigInteger('file_indexing_id')->nullable()->index();

            // Core File Details
            $table->string('file_number', 255)->comment('The related file number');
            $table->string('file_title', 255)->nullable();
            $table->string('plot_number', 255)->nullable();
            $table->string('tp_no', 255)->nullable();
            $table->string('lpkn_no', 255)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('indexing_type', 50)->nullable();

            // Audit & Tracking (Maintained for logic consistency)
            $table->string('tracking_id', 255)->nullable();
            $table->string('created_by', 255)->nullable();
            $table->string('updated_by', 255)->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('file_indexing_links');
    }
};
