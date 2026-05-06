<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('payroll_attendance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('period_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->decimal('login_days', 6, 2)->default(0);
            $table->decimal('hours_worked', 8, 2)->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->json('session_breakdown')->nullable();
            $table->string('source_reference')->nullable();
            $table->timestamps();

            $table->unique(['period_id', 'user_id']);
            $table->index(['period_id', 'department_id']);
            $table->index('department_id');
            $table->foreign('period_id')->references('id')->on('payroll_periods')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('payroll_attendance');
    }
};
