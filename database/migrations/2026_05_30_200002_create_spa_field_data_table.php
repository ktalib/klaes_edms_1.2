<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection($this->connection)->create('spa_field_data', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('spa_application_id')->index();
            $table->string('file_number', 255)->nullable();
            $table->unsignedBigInteger('surveyor_id')->nullable(); // FK users.id
            $table->date('inspection_date')->nullable();
            $table->text('coordinates')->nullable(); // JSON: {"lat":..., "lng":...}
            $table->text('findings')->nullable();
            $table->text('photos')->nullable();      // JSON: array of storage paths
            $table->string('status', 30)->default('pending'); // pending | verified
            $table->string('created_by', 255)->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('spa_field_data');
    }
};
