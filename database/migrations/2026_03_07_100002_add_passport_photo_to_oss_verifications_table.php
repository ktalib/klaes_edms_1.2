<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up()
    {
        Schema::connection('sqlsrv')->table('oss_verifications', function (Blueprint $table) {
            $table->string('passport_photo', 500)->nullable()->after('chairman_name');
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('oss_verifications', function (Blueprint $table) {
            $table->dropColumn('passport_photo');
        });
    }
};
