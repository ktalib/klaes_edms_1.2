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
    protected $connection = 'sqlsrv';

    public function up()
    {
        Schema::connection('sqlsrv')->table('change_of_purpose_applications', function (Blueprint $table) {
            $table->string('new_purpose', 500)->nullable()->after('purpose');
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('change_of_purpose_applications', function (Blueprint $table) {
            $table->dropColumn('new_purpose');
        });
    }
};
