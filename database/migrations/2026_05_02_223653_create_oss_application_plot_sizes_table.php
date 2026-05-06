<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Create oss_application_plot_sizes table
        Schema::connection('sqlsrv')->create('oss_application_plot_sizes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('oss_application_id');
            $table->string('plot_number', 50)->nullable();
            $table->decimal('plot_size', 18, 2)->nullable();
            $table->string('type', 30); // subdivision_fragment, merger_source
            $table->timestamps();

            $table->foreign('oss_application_id')->references('id')->on('oss_applications')->onDelete('cascade');
        });

        // 2. Add columns to oss_applications table
        Schema::connection('sqlsrv')->table('oss_applications', function (Blueprint $table) {
            $table->string('temp_file_no', 100)->nullable();
            $table->string('file_title', 500)->nullable();
            $table->string('house_no', 100)->nullable();
            $table->string('street_name', 255)->nullable();
            $table->string('state', 100)->nullable();
            $table->integer('num_plots')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('oss_applications', function (Blueprint $table) {
            $table->dropColumn(['temp_file_no', 'file_title', 'house_no', 'street_name', 'state', 'num_plots']);
        });

        Schema::connection('sqlsrv')->dropIfExists('oss_application_plot_sizes');
    }
};
