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
        Schema::connection('sqlsrv')->create('oss_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instrument_capture_id')->nullable()->index();
            $table->string('file_no', 120)->nullable()->index();
            $table->string('signature_name', 255)->nullable();
            $table->unsignedBigInteger('captured_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('oss_acknowledgements');
    }
};
