<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('sqlsrv')->table('gkn_file_no', function (Blueprint $table) {
            $table->string('customer_type', 50)->nullable()->after('type');
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('gkn_file_no', function (Blueprint $table) {
            $table->dropColumn('customer_type');
        });
    }
};
