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
        Schema::connection('sqlsrv')->create('sltr_shadow_files', function (Blueprint $table) {
            $table->id();
            $table->string('ref_number')->nullable();
            $table->string('full_number')->nullable();
            $table->string('file_name', 500)->nullable();
            $table->string('plot_no')->nullable();
            $table->string('location')->nullable();
            $table->string('lga')->nullable();
            $table->string('tracking_id')->nullable();
            $table->integer('created_by')->nullable();
            $table->date('date_matched')->nullable();
            $table->time('time_matched')->nullable();
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
        Schema::connection('sqlsrv')->dropIfExists('sltr_shadow_files');
    }
};
