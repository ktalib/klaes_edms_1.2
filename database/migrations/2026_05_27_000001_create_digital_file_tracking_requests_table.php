<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection($this->connection)->create('digital_file_tracking_requests', function (Blueprint $table) {
            $table->id();

            // Auto-generated request reference
            $table->string('request_no', 30)->unique();

            // File being requested
            $table->string('file_no', 100)->nullable();
            $table->string('file_title')->nullable();

            // People involved
            $table->unsignedBigInteger('requester_user_id');
            $table->string('sending_officer');            // logged-in user full name
            $table->string('receiving_officer')->nullable(); // officer at destination

            // Offices
            $table->unsignedBigInteger('source_office_id')->nullable();
            $table->string('source_office_name')->nullable();
            $table->unsignedBigInteger('destination_office_id')->nullable();
            $table->string('destination_office_name')->nullable();

            // Where the file actually is at time of request
            $table->string('current_file_location')->nullable();  // office name
            $table->string('current_file_holder')->nullable();     // officer name

            // Was the request redirected to the current holder instead of the registry?
            $table->boolean('is_redirected')->default(false);

            // Status
            $table->string('request_status', 30)->default('Pending');
            // Pending | Approved | Rejected | Redirected | In Transit | Completed | Canceled

            // Remarks / notes
            $table->text('remarks')->nullable();
            $table->text('rejection_reason')->nullable();

            // Timestamps for key actions
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('digital_file_tracking_requests');
    }
};
