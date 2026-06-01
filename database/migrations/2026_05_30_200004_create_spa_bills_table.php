<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection($this->connection)->create('spa_bills', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('spa_application_id')->index();
            $table->string('bill_type', 100)->nullable();   // e.g. Change of Purpose Fee, Processing Fee
            $table->string('description', 500)->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('reference_id', 100)->nullable()->unique();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('unpaid'); // unpaid | paid | partial
            $table->string('created_by', 255)->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('spa_bills');
    }
};
