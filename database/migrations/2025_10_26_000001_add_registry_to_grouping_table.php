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

        if ($schema->hasColumn('grouping', 'registry')) {
            return;
        }

        $schema->table('grouping', function (Blueprint $table) {
            $table->string('registry', 150)->nullable()->after('indexed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasColumn('grouping', 'registry')) {
            return;
        }

        $schema->table('grouping', function (Blueprint $table) {
            $table->dropColumn('registry');
        });
    }
};
