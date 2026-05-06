<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('deeds_bill_balances_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('file_number');
            $table->string('applicant_name');
            $table->string('applicant_address')->nullable();
            $table->string('location_station')->nullable();
            $table->string('district')->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('status', 40)->default('draft');
            $table->text('notes')->nullable();
            $table->string('prepared_by')->nullable();
            $table->dateTime('prepared_at')->nullable();
            $table->unsignedBigInteger('billing_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('deeds_bill_balances_metadata');
    }
};
