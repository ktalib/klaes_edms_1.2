<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('sqlsrv')->create('for_information', function (Blueprint $table) {
            $table->id();
            $table->string('serial_no')->nullable();
            $table->string('ref_no')->nullable();
            $table->string('title')->nullable();
            $table->string('commencement_day')->nullable();
            $table->string('signer_name')->nullable();
            $table->string('signer_rank')->nullable();
            $table->string('status')->nullable();
            $table->unsignedInteger('print_count')->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('for_information');
    }
};
