<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->create('file_indexing_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('billing_id')->nullable()->index();   // FK -> billing.id
            $table->unsignedBigInteger('file_indexing_id')->nullable()->index();
            $table->string('file_number')->nullable()->index();
            // 'bill-balance' or 'grant-rent'
            $table->string('bill_type', 50);
            $table->decimal('amount', 18, 2)->nullable();
            $table->integer('from_year')->nullable();
            $table->integer('to_year')->nullable();
            $table->string('receipt_no')->nullable();
            $table->date('receipt_date')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('file_indexing_bills');
    }
};
