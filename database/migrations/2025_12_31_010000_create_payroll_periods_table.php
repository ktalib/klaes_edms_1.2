<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('period_code', 7)->comment('YYYY-MM representation of the payroll period');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 32)->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamps();

            $table->unique('period_code');
            $table->index(['start_date', 'end_date']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            // Avoid multiple cascade paths in SQL Server by leaving locked_by as NO ACTION on delete
            $table->foreign('locked_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('payroll_periods');
    }
};
