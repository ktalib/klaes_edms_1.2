<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->create('land_temporary_file_numbers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('applicant_name', 255);
            $table->string('file_no', 255)->nullable();
            $table->string('plot_no', 100)->nullable();
            $table->string('district', 255)->nullable();
            $table->string('lga', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 50)->default('Pending');
            $table->timestamp('recommendation_generated_at')->nullable();
            $table->unsignedBigInteger('captured_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('land_temporary_file_numbers');
    }
};
