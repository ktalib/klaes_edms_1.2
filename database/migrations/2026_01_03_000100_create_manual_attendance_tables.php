<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('manual_attendance_batches')) {
            Schema::connection('sqlsrv')->create('manual_attendance_batches', function (Blueprint $table) {
                $table->id();
                $table->string('batch_code', 50)->unique();
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status', 20)->default('draft');
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['period_start', 'period_end']);
                $table->index('status');
                $table->foreign('submitted_by')->references('id')->on('users')->onDelete('no action');
                $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('no action');
            });
        }

        if (!Schema::connection('sqlsrv')->hasTable('manual_attendance_entries')) {
            Schema::connection('sqlsrv')->create('manual_attendance_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('batch_id');
                $table->unsignedBigInteger('user_id');
                $table->date('attendance_date');
                $table->string('shift_code', 30)->nullable();
                $table->decimal('login_hours', 6, 2)->default(0);
                $table->decimal('overtime_hours', 6, 2)->default(0);
                $table->string('status', 20)->default('draft');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('captured_by');
                $table->timestamp('captured_at')->useCurrent();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'attendance_date']);
                $table->index(['attendance_date', 'status']);
                $table->index('batch_id');
                $table->foreign('batch_id')->references('id')->on('manual_attendance_batches')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->onDelete('no action');
                $table->foreign('captured_by')->references('id')->on('users')->onDelete('no action');
                $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('no action');
            });
        }

        if (!Schema::connection('sqlsrv')->hasTable('manual_attendance_audit_logs')) {
            Schema::connection('sqlsrv')->create('manual_attendance_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('entry_id');
                $table->string('action', 50);
                $table->unsignedBigInteger('performed_by')->nullable();
                $table->timestamp('performed_at')->useCurrent();
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->index(['entry_id', 'action']);
                $table->index('performed_at');
                $table->foreign('entry_id')->references('id')->on('manual_attendance_entries')->cascadeOnDelete();
                $table->foreign('performed_by')->references('id')->on('users')->onDelete('no action');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('manual_attendance_audit_logs');
        Schema::connection('sqlsrv')->dropIfExists('manual_attendance_entries');
        Schema::connection('sqlsrv')->dropIfExists('manual_attendance_batches');
    }
};
