<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create scheduled_activity_reports table
 * 
 * Stores configuration for recurring activity log report generation and email delivery
 */
class CreateScheduledActivityReportsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('scheduled_activity_reports', function (Blueprint $table) {
            // Primary key
            $table->id();

            // User relationship
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Schedule configuration
            $table->enum('frequency', ['daily', 'weekly', 'biweekly', 'monthly'])->default('daily');
            $table->enum('format', ['csv', 'pdf', 'excel'])->default('csv');

            // Report recipients (comma-separated emails)
            $table->string('recipients', 500);

            // Date range for reports (nullable for "all time" reports)
            $table->date('report_date_from')->nullable();
            $table->date('report_date_to')->nullable();

            // Schedule status
            $table->boolean('is_active')->default(true);

            // Next scheduled run time
            $table->dateTime('next_run_at');

            // Last run information
            $table->dateTime('last_run_at')->nullable();
            $table->integer('run_count')->default(0);
            $table->dateTime('last_failure_at')->nullable();
            $table->text('last_failure_reason')->nullable();

            // Report filtering options (JSON)
            $table->json('filters')->nullable();
            // Example: {"status": "ACTIVE", "include_charts": true}

            // Audit trail
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->softDeletes();

            // Indexes for performance
            $table->index('user_id');
            $table->index('is_active');
            $table->index('next_run_at');
            $table->index('frequency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('scheduled_activity_reports');
    }
}
