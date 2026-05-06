<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->create('change_of_name_applications', function (Blueprint $table) {
            $table->id();
            $table->string('file_no', 255);
            $table->string('current_name', 500)->nullable();
            $table->string('title', 50)->nullable();
            $table->string('first_name', 255);
            $table->string('middle_name', 255)->nullable();
            $table->string('last_name', 255);
            $table->string('new_full_name', 500);
            $table->string('land_use', 255)->nullable();
            $table->string('location', 2000)->nullable();
            $table->string('plot_no', 100)->nullable();
            $table->string('plan_no', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('residential_address', 2000)->nullable();
            $table->string('status', 50)->default('pending');
            $table->text('remarks')->nullable();
            $table->string('comment', 2000)->nullable();
            $table->unsignedBigInteger('captured_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('change_of_name_applications');
    }
};
