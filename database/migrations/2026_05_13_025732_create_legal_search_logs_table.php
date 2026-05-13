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
        Schema::connection('sqlsrv')->create('legal_search_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('search_parameter')->nullable();
            $table->string('search_value')->nullable();
            $table->string('result_status')->default('Not Found');
            $table->integer('results_count')->default(0);
            $table->string('lga')->nullable();
            $table->string('receipt_no')->nullable();
            $table->boolean('printed')->default(false);
            $table->text('direct_link')->nullable();
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
        Schema::connection('sqlsrv')->dropIfExists('legal_search_logs');
    }
};
