<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection($this->connection)->create('spa_certificates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('spa_application_id')->index();
            $table->string('cert_number', 50)->nullable()->unique(); // SPA-COP/YYYY/####
            $table->string('file_number', 255)->nullable();
            $table->string('holder_name', 255)->nullable();
            $table->string('from_use', 255)->nullable();
            $table->string('to_use', 255)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedBigInteger('issued_by')->nullable(); // FK users.id
            $table->string('status', 20)->default('draft'); // draft | issued | collected | revoked
            $table->string('created_by', 255)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('file_number');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('spa_certificates');
    }
};
