<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('payroll_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('period_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->comment('Target user affected by the action');
            $table->unsignedBigInteger('actor_id')->nullable()->comment('Administrator performing the action');
            $table->string('action', 64);
            $table->json('payload')->nullable();
            $table->string('source', 64)->nullable();
            $table->timestamps();

            $table->index('action');
            $table->index('created_at');
            $table->foreign('period_id')->references('id')->on('payroll_periods')->nullOnDelete();
            // Preserve audit rows if users are removed; rely on NO ACTION to avoid cascade conflicts
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('actor_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('payroll_audit_logs');
    }
};
