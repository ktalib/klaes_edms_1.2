<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('sqlsrv')->create('survey_report_requests', function (Blueprint $table) {
            $table->id();
            $table->string('serial_no')->nullable();
            $table->date('director_cadastral_date')->nullable();
            $table->string('director_name')->nullable();
            $table->string('director_rank')->nullable();
            $table->string('director_signature')->nullable();
            $table->string('rofo_no')->nullable();
            $table->string('item_g_no')->nullable();
            $table->text('plot_description')->nullable();
            $table->string('survey_necessary')->nullable();
            $table->text('beacon_numbers')->nullable();
            $table->date('director_cadastral_approval_date')->nullable();
            $table->date('commissioner_date')->nullable();
            $table->string('status')->nullable();
            $table->unsignedInteger('print_count')->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('survey_report_requests');
    }
};
