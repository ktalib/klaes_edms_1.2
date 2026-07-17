<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Physical shelf map: which file-number series and serial range live on each
 * rack/shelf, per registry. Sourced from the "FileNo Combination" workbooks in
 * docs/data/shalf_racks and loaded by `shelf-racks:import`.
 *
 * Two workbook sets exist and disagree on 56 shelves (the older set labels them
 * CON-RES-*, the newer _2_ set labels the same serial ranges RES-*), so both are
 * kept and tagged with source_file / set_version rather than reconciled here.
 * That is why (registry_id, rack, shelf) is indexed but NOT unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('shelf_rack_ranges')) {
            return;
        }

        Schema::connection('sqlsrv')->create('shelf_rack_ranges', function (Blueprint $table) {
            $table->id();

            // physical_registries.id — 1 (Registry 1 - Land) or 3 (Registry 3 - Land).
            $table->unsignedBigInteger('registry_id');

            $table->string('rack', 10);
            $table->unsignedSmallInteger('shelf');
            $table->string('rack_shelf', 20);

            // Null where the workbook lists a shelf but leaves it unallocated.
            $table->string('file_no', 50)->nullable();

            // Parsed from "SERIALNO RANGE"; serial_range keeps the raw cell text
            // because the sheets are inconsistently spaced ("701 -800").
            $table->unsignedInteger('serial_from')->nullable();
            $table->unsignedInteger('serial_to')->nullable();
            $table->string('serial_range', 50)->nullable();

            // Provenance. set_version 1 = "FileNo Combination_Rack *.xlsx",
            // 2 = "FileNo Combination_2_Rack *.xlsx".
            $table->string('source_file', 150);
            $table->unsignedTinyInteger('set_version');
            $table->unsignedInteger('source_sn')->nullable();

            // Best-effort link to Rack_Shelf_Labels.id; null for the 121 labels
            // in the workbooks that have no row there yet. Deliberately no FK.
            $table->unsignedBigInteger('shelf_label_id')->nullable();

            $table->timestamps();

            $table->index(['registry_id', 'rack', 'shelf'], 'shelf_rack_ranges_registry_rack_shelf_idx');
            $table->index('file_no', 'shelf_rack_ranges_file_no_idx');
            $table->index('rack_shelf', 'shelf_rack_ranges_rack_shelf_idx');
            $table->index('shelf_label_id', 'shelf_rack_ranges_shelf_label_idx');

            // Idempotency key for the importer: a rack/shelf appears at most once
            // per workbook (verified across all 41 files).
            $table->unique(['source_file', 'rack', 'shelf'], 'shelf_rack_ranges_source_rack_shelf_unq');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('shelf_rack_ranges');
    }
};
