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
        Schema::connection('sqlsrv')->table('kangis_checkout_approvals', function (Blueprint $table) {
            $table->string('module', 40)->nullable()->after('status')->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('kangis_checkout_approvals', function (Blueprint $table) {
            $table->dropColumn('module');
        });
    }
};
