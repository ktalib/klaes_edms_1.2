<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One step of undo for a batch draft.
 *
 * A draft is overwritten by every autosave, so anything that corrupts the table
 * on screen — a restore landing badly, a stray reload of the children — is
 * written straight over the good copy a few seconds later, with nothing left to
 * go back to. Each save now pushes the copy it replaces into payload_previous,
 * which makes that class of accident recoverable instead of terminal.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up()
    {
        if (Schema::connection('sqlsrv')->hasColumn('land_recommendation_batch_drafts', 'payload_previous')) {
            return;
        }

        Schema::connection('sqlsrv')->table('land_recommendation_batch_drafts', function (Blueprint $table) {
            $table->text('payload_previous')->nullable()->after('payload');
            // How much was in the copy being kept, so the "restore the previous
            // version" offer can say what it would bring back without decoding it.
            $table->integer('previous_children_total')->nullable()->after('payload_previous');
            $table->dateTime('previous_saved_at')->nullable()->after('previous_children_total');
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('land_recommendation_batch_drafts', function (Blueprint $table) {
            $table->dropColumn(['payload_previous', 'previous_children_total', 'previous_saved_at']);
        });
    }
};
