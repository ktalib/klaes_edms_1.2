<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedicated relationship registry for files involved in a parcel-update event
 * (Change of Purpose, Plot Subdivision, Plot Merger, Plot Extension, Plot Separation,
 * and Temporary-File consolidation). One row per file participating in a group.
 *
 * The lineage itself already exists in decommissioned_files (old -> successor_file_no)
 * and file_indexings.related_fileno / parent_prop_id; this table materialises it into
 * a single stable group keyed by MergerID so the File Tracking Sheet / File Movement
 * History can stitch every related file's movement log into one continuous timeline.
 *
 * Populated automatically: backfilled from existing lineage (file-merger:rebuild) and
 * refreshed on decommission via PlotWorkflowService, plus lazily on read.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('file_merger')) {
            return;
        }

        Schema::connection('sqlsrv')->create('file_merger', function (Blueprint $table) {
            $table->bigIncrements('id');

            // MergerID — common identifier shared by every file in the same group.
            $table->string('merger_id', 64)->index();

            // FMergeID — unique identifier for each file entry within the merger.
            $table->string('fmerge_id', 64)->unique();

            // parent | child (a mid-chain file can be recorded per edge; role reflects
            // this row's position relative to the event that produced the group).
            $table->string('role', 16)->nullable();

            // The immediate predecessor file number(s) this row descends from (null for
            // the root/decommissioned parent) and the successor file number(s) it feeds.
            $table->string('parent_file', 255)->nullable();
            $table->string('child_file', 255)->nullable();

            // This row's own file identity + descriptive fields.
            $table->string('file_number', 255)->index();
            $table->string('file_title', 500)->nullable();
            $table->string('location', 500)->nullable();

            $table->dateTime('date_commissioned')->nullable();
            $table->dateTime('date_decommissioned')->nullable();

            // Provenance of the row (backfill | workflow | read-rebuild) + the update type.
            $table->string('source', 32)->nullable();
            $table->string('reason', 255)->nullable();

            $table->timestamps();

            // A file appears at most once per group.
            $table->unique(['merger_id', 'file_number'], 'file_merger_group_file_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('file_merger');
    }
};
