<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist Purpose on pra.
 *
 * Batch-captured OPs carry a Purpose chosen on the Capture OP card, but pra had nowhere to
 * store it — so Purpose only survived in-memory, via the backfill into the commission record's
 * purpose_id. That made it unrecoverable when reopening a saved batch for editing: every
 * record came back with Purpose blank, and the card requires one.
 *
 * purpose_id is the exact reference (purposes.id); purpose keeps the name for readability in
 * reports and exports. No foreign key — 2026_02_12_053000 deliberately dropped the FKs between
 * purposes and landuses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('pra', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('pra', 'purpose')) {
                $table->string('purpose', 255)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('pra', 'purpose_id')) {
                $table->unsignedBigInteger('purpose_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('pra', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('pra', 'purpose_id')) {
                $table->dropColumn('purpose_id');
            }
            if (Schema::connection('sqlsrv')->hasColumn('pra', 'purpose')) {
                $table->dropColumn('purpose');
            }
        });
    }
};
