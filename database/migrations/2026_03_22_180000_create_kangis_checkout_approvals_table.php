<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('kangis_checkout_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('request_no', 40)->unique();
            // file_tracker_id is nullable — requests may be freeform (file not yet in tracker)
            $table->unsignedBigInteger('file_tracker_id')->nullable();
            $table->string('file_number', 120)->nullable();
            $table->string('file_title', 255)->nullable();
            // Requester details
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('requested_by_name', 255)->nullable();
            $table->string('requested_office_code', 60)->nullable();
            $table->string('requested_office_name', 255)->nullable();
            $table->text('purpose')->nullable();
            $table->date('expected_return_date')->nullable();
            // Approval state: pending | approved | rejected
            $table->string('status', 20)->default('pending');
            // Approval side
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('approved_by_name', 255)->nullable();
            $table->string('approved_by_designation', 255)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('acknowledgement_no', 60)->nullable();
            // Rejection side
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->string('rejected_by_name', 255)->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('kangis_checkout_approvals');
    }
};
