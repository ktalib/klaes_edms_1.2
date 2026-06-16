<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (Schema::connection('sqlsrv')->hasTable('file_search_requests')) {
            return;
        }

        Schema::connection('sqlsrv')->create('file_search_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no', 30)->unique();
            $table->string('file_number', 255);
            $table->string('file_title', 255)->nullable();
            $table->unsignedBigInteger('requester_user_id')->nullable();
            $table->unsignedBigInteger('assigned_monitor_id')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->string('resolved_status', 50)->nullable();
            $table->string('current_location', 255)->nullable();
            $table->text('feedback_note')->nullable();
            $table->unsignedBigInteger('responded_by')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->timestamps();

            $table->index('file_number');
            $table->index('status');
            $table->index('assigned_monitor_id');
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('file_search_requests');
    }
};
