<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quarantine table for indexed files found to be duplicates.
 *
 * "Move to Indexing Duplicates" hard-deletes the record from file_indexings,
 * fileNumber, customers_staging and entities_staging, so this table is the only
 * remaining trace. It therefore stores a full JSON snapshot of every deleted row
 * (`snapshot`) rather than just a reference — without it the move would be
 * unrecoverable. The flat columns exist so the list can be searched and displayed
 * without parsing the snapshot.
 *
 * Note this is NOT duplicate_fileno: that table flags a file number as having a
 * known duplicate while leaving every record in place (the "IsDuplicate" action).
 * This table holds records that have been removed from the live system.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('indexing_duplicates')) {
            return;
        }

        Schema::connection('sqlsrv')->create('indexing_duplicates', function (Blueprint $table) {
            $table->id();

            // Deliberately not a foreign key: the file_indexings row is gone by the
            // time this row is committed.
            $table->unsignedBigInteger('file_indexing_id')->nullable();

            $table->string('file_number', 100);
            $table->string('registry', 100)->nullable();
            $table->string('general_registry', 100)->nullable();
            $table->string('file_title', 255)->nullable();
            $table->string('plot_number', 100)->nullable();
            $table->string('land_use_type', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('lga', 100)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('tracking_id', 100)->nullable();
            $table->integer('prop_id')->nullable();
            $table->string('kangis_file_no', 100)->nullable();
            $table->string('new_kangis_file_no', 100)->nullable();
            $table->string('mls_file_no', 100)->nullable();

            // The file number that survives, when the operator names it.
            $table->string('duplicate_of', 100)->nullable();
            $table->string('reason', 500)->nullable();

            // Provenance of the original indexing, carried over so the moved record
            // still shows who indexed it and when.
            $table->string('indexed_by', 150)->nullable();
            $table->dateTime('indexed_at')->nullable();

            // Set when a commissioning row was found in mls_file_no and left in
            // place — that table is intentionally outside the cascade.
            $table->boolean('mls_file_no_retained')->default(false);

            $table->text('snapshot')->nullable();
            $table->text('deleted_counts')->nullable();

            $table->string('moved_by', 150)->nullable();
            $table->unsignedBigInteger('moved_by_id')->nullable();

            $table->dateTime('restored_at')->nullable();
            $table->string('restored_by', 150)->nullable();

            $table->timestamps();

            $table->index('file_number', 'idx_indexing_duplicates_file_number');
            $table->index('file_indexing_id', 'idx_indexing_duplicates_file_indexing_id');
            $table->index('prop_id', 'idx_indexing_duplicates_prop_id');
            $table->index('created_at', 'idx_indexing_duplicates_created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('indexing_duplicates');
    }
};
