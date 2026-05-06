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
        Schema::connection('sqlsrv')->create('gkn_file_no', function (Blueprint $table) {
            $table->id();
            $table->integer('year')->nullable();
            $table->integer('serial_number')->nullable();
            $table->string('full_file_number', 100)->nullable();
            $table->string('file_name', 500)->nullable();
            $table->string('plot_no', 100)->nullable();
            $table->text('location')->nullable();
            $table->string('lga', 100)->nullable();
            $table->string('tracking_id', 100)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('commissioning_date')->nullable();
            $table->boolean('is_deleted')->default(0);
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
        Schema::connection('sqlsrv')->dropIfExists('gkn_file_no');
    }
};
