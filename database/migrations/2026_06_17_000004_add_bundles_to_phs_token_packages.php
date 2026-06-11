<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::connection('sqlsrv')->hasColumn('phs_token_packages', 'bundles')) {
            Schema::connection('sqlsrv')->table('phs_token_packages', function (Blueprint $table) {
                $table->integer('bundles')->default(1)->after('slug');
            });
        }

        // Backfill bundles from existing token counts (1 bundle = 1000 tokens).
        DB::connection('sqlsrv')->table('phs_token_packages')
            ->update(['bundles' => DB::raw('CASE WHEN tokens / 1000 < 1 THEN 1 ELSE tokens / 1000 END')]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::connection('sqlsrv')->hasColumn('phs_token_packages', 'bundles')) {
            Schema::connection('sqlsrv')->table('phs_token_packages', function (Blueprint $table) {
                $table->dropColumn('bundles');
            });
        }
    }
};
