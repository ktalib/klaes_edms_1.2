<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('sqlsrv')->table('pra', function (Blueprint $table) {
            $table->string('rofo_number', 255)->nullable()->after('regNo');
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('pra', function (Blueprint $table) {
            $table->dropColumn('rofo_number');
        });
    }
};
