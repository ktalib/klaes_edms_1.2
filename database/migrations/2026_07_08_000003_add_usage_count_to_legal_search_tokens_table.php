<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a usage counter so a single token may be spent up to 2 times per file
     * number (Pay-Per-Search). The legacy `is_used` boolean is retained and kept
     * in sync (true once the token is fully exhausted).
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->table('legal_search_tokens', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('legal_search_tokens', 'usage_count')) {
                $table->unsignedTinyInteger('usage_count')->default(0)->after('is_used');
            }
        });

        // Back-fill: existing "used" tokens have been consumed once.
        DB::connection('sqlsrv')->table('legal_search_tokens')
            ->where('is_used', true)
            ->where('usage_count', 0)
            ->update(['usage_count' => 1]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('legal_search_tokens', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('legal_search_tokens', 'usage_count')) {
                $table->dropColumn('usage_count');
            }
        });
    }
};
