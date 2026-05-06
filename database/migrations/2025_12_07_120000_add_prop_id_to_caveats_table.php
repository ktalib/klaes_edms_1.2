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
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasTable('caveats') || $schema->hasColumn('caveats', 'prop_id')) {
            return;
        }

        $schema->table('caveats', function (Blueprint $table) {
            $table->unsignedBigInteger('prop_id')
                ->nullable()
                ->comment('Shared property identifier for cross-module joins');
            $table->index('prop_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasTable('caveats') || ! $schema->hasColumn('caveats', 'prop_id')) {
            return;
        }

        $schema->table('caveats', function (Blueprint $table) {
            $table->dropIndex(['prop_id']);
            $table->dropColumn('prop_id');
        });
    }
};
