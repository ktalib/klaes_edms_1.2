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
        Schema::connection('sqlsrv')->create('phs_search_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('phs_institution_id');
            $table->unsignedBigInteger('phs_member_id')->nullable();
            $table->string('query', 255)->nullable();
            $table->string('file_number', 100)->nullable();
            $table->integer('result_count')->default(0);
            $table->string('reference_no', 100)->nullable();
            $table->integer('tokens_used')->default(1);
            $table->timestamps();

            $table->index('phs_institution_id');
            $table->index('reference_no');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('phs_search_logs');
    }
};
