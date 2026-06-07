<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phs_onboarding_requests', function (Blueprint $table) {
            $table->id();

            // Request details from form
            $table->string('organization_name', 255);
            $table->enum('organization_type', ['bank', 'law_firm', 'corporate']);
            $table->string('contact_name', 255);
            $table->string('contact_email', 191);
            $table->string('phone', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('department', 255)->nullable();
            $table->string('job_title', 255)->nullable();
            $table->string('initial_token_package', 255)->nullable();
            $table->text('additional_notes')->nullable();

            // Workflow status
            $table->enum('status', ['pending', 'payment_received', 'approved', 'rejected', 'activated'])
                  ->default('pending')
                  ->index();

            // Payment info
            $table->string('payment_reference', 255)->nullable();
            $table->decimal('payment_amount', 10, 2)->nullable();
            $table->timestamp('payment_received_at')->nullable();

            // Approval tracking
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('rejection_reason')->nullable();

            // Activation token for registration link
            $table->string('activation_token', 255)->nullable()->unique();
            $table->timestamp('activation_token_expires_at')->nullable();

            // Link to created institution after activation
            $table->unsignedBigInteger('created_phs_institution_id')->nullable();
            $table->foreign('created_phs_institution_id')
                  ->references('id')
                  ->on('phs_institutions')
                  ->onDelete('set null');

            $table->timestamps();

            // Indexes
            $table->index('contact_email');
            $table->index('status');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phs_onboarding_requests');
    }
};
