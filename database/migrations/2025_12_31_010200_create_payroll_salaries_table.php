<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('payroll_salaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('period_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('base_salary', 14, 2)->default(0);
            $table->decimal('bonuses', 14, 2)->default(0);
            $table->decimal('extra_hours_value', 14, 2)->default(0);
            $table->decimal('deductions', 14, 2)->default(0);
            $table->decimal('net_salary', 14, 2)->default(0);
            $table->timestamp('computed_at')->nullable();
            $table->string('calculation_status', 32)->default('pending');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['period_id', 'user_id']);
            $table->index('net_salary');
            $table->foreign('period_id')->references('id')->on('payroll_periods')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('payroll_salaries');
    }
};
