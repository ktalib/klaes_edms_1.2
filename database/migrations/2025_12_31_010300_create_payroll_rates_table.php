<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('payroll_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->decimal('daily_rate', 12, 2);
            $table->unsignedTinyInteger('shift_hours');
            $table->date('effective_date');
            $table->date('expires_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'effective_date']);
            $table->index('is_active');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            // Leave audit foreign keys as NO ACTION to satisfy SQL Server cascade rules
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('payroll_rates');
    }
};
