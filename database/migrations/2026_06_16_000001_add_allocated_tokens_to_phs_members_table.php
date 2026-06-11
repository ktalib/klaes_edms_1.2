<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        if (!Schema::connection('sqlsrv')->hasColumn('phs_members', 'allocated_tokens')) {
            Schema::connection('sqlsrv')->table('phs_members', function (Blueprint $table) {
                $table->integer('allocated_tokens')->default(0)->after('tokens_used');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::connection('sqlsrv')->hasColumn('phs_members', 'allocated_tokens')) {
            Schema::connection('sqlsrv')->table('phs_members', function (Blueprint $table) {
                $table->dropColumn('allocated_tokens');
            });
        }
    }
};
