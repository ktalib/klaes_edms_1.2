<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            // Bill Balance
            $table->decimal('bill_total_amount', 18, 2)->nullable();
            $table->string('bill_receipt_no')->nullable();
            $table->date('bill_receipt_date')->nullable();

            // Grant Rent
            $table->decimal('ground_rent_amount', 18, 2)->nullable();
            $table->integer('ground_rent_from_year')->nullable();
            $table->integer('ground_rent_to_year')->nullable();
            $table->string('ground_rent_receipt_no')->nullable();
            $table->date('ground_rent_receipt_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            $table->dropColumn([
                'bill_total_amount',
                'bill_receipt_no',
                'bill_receipt_date',
                'ground_rent_amount',
                'ground_rent_from_year',
                'ground_rent_to_year',
                'ground_rent_receipt_no',
                'ground_rent_receipt_date',
            ]);
        });
    }
};
