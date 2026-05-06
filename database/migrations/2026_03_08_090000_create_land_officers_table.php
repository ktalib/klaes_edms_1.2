<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('land_officers')) {
            Schema::connection('sqlsrv')->create('land_officers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('rank')->nullable();
                $table->string('signature_file', 500)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('verification_code', 20)->nullable();
                $table->dateTime('verification_code_expires_at')->nullable();
                $table->string('notification_type', 20)->default('email');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('land_officers');
    }
};
