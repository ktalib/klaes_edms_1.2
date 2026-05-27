<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('sqlsrv')->table('buyer_list', function (Blueprint $table) {
            $table->string('block_no', 50)->nullable()->after('section_number');
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('buyer_list', function (Blueprint $table) {
            $table->dropColumn('block_no');
        });
    }
};
