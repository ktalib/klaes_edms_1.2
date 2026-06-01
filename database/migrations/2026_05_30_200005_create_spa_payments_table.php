<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection($this->connection)->create('spa_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('spa_bill_id')->index();
            $table->unsignedBigInteger('spa_application_id')->index();
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('receipt_number', 100)->nullable();
            $table->date('payment_date')->nullable();
            $table->string('payment_method', 50)->nullable(); // cash | transfer | online
            $table->string('recorded_by', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('spa_payments');
    }
};
