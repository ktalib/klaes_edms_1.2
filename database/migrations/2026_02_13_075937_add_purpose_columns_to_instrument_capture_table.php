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
        Schema::connection('sqlsrv')->table('instrument_capture', function (Blueprint $table) {
            $table->string('purpose', 255)->nullable()->after('land_use');
            $table->integer('land_use_id')->nullable()->after('purpose');
            $table->integer('purpose_id')->nullable()->after('land_use_id');
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('instrument_capture', function (Blueprint $table) {
            $table->dropColumn(['purpose', 'land_use_id', 'purpose_id']);
        });
    }
};
