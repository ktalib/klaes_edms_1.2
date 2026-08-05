<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autosave store for the Plot Subdivision batch capture.
 *
 * A subdivision can run past a hundred children, and keying that many rows takes
 * far longer than a session lives — the form used to be lost whole to a timeout
 * or a 419 on submit. The capture now writes itself here every few seconds, so
 * the work survives the session that was keying it and can be resumed later.
 *
 * A draft is deliberately schema-free: `payload` holds the form exactly as the
 * browser had it (common fields + every child row, ticked or not). Nothing here
 * is authoritative — the real validation still happens in storeBatch() when the
 * draft is finally submitted.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up()
    {
        if (Schema::connection('sqlsrv')->hasTable('land_recommendation_batch_drafts')) {
            return;
        }

        Schema::connection('sqlsrv')->create('land_recommendation_batch_drafts', function (Blueprint $table) {
            $table->id();

            // Minted by the browser when a batch capture starts, and the only key
            // the autosave knows — the row id is not available until the first
            // save has already come back.
            $table->string('draft_key', 40)->unique();

            // Drafts are private to the user who keyed them: a half-filled batch
            // is not a shared work item, and two officers resuming the same draft
            // would silently overwrite each other.
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->string('application_type', 60)->nullable();
            $table->string('mother_file_no', 100)->nullable()->index();

            // Denormalised off the payload purely so the "resume a draft" list can
            // show "MLKN 1234 — 96 of 118 children" without parsing every payload.
            $table->integer('children_total')->default(0);
            $table->integer('children_selected')->default(0);

            // The whole capture, as JSON. nvarchar(max): a 100+ child batch runs to
            // hundreds of KB, well past the 4000-char default.
            $table->text('payload')->nullable();

            // open      — still being keyed, offered for resume
            // submitted — storeBatch() saved it; kept for the audit trail
            // discarded — the user threw it away
            $table->string('status', 20)->default('open')->index();

            // Set when the draft becomes a real batch, so a draft can be traced to
            // the recommendations it produced.
            $table->string('rofo_batch_id', 60)->nullable();

            // Separate from updated_at: this is the moment the *browser* last had
            // its work accepted, which is what the "Saved 12:04" badge reports.
            $table->dateTime('last_saved_at')->nullable();

            $table->timestamps();

            // The resume list: this user's open drafts, newest first.
            $table->index(['user_id', 'status', 'last_saved_at'], 'idx_lr_batch_drafts_user_status');
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('land_recommendation_batch_drafts');
    }
};
