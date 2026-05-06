<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create audit_logs table
 *
 * Tracks all important system actions for compliance, security, and audit purposes
 */
class CreateAuditLogsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('audit_logs', function (Blueprint $table) {
            // Primary key
            $table->id();

            // User information
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            // Action details
            $table->string('action', 50); // CREATED, UPDATED, DELETED, DOWNLOADED, EXPORTED, VIEWED, etc.
            $table->string('resource_type', 100); // report, scheduled_report, user_activity_log, export, etc.
            $table->unsignedBigInteger('resource_id')->nullable(); // ID of affected resource

            // Changes tracking (JSON)
            $table->json('old_values')->nullable(); // Previous values
            $table->json('new_values')->nullable(); // New values

            // Request context
            $table->string('ip_address', 45)->nullable(); // IPv4 or IPv6
            $table->longText('user_agent')->nullable(); // Full user agent string

            // Timestamps
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->softDeletes();

            // Indexes for performance
            $table->index('user_id');
            $table->index('action');
            $table->index('resource_type');
            $table->index('resource_id');
            $table->index('created_at');
            $table->index(['resource_type', 'resource_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('audit_logs');
    }
}
