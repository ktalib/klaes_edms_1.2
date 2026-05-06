<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->create('file_number_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 10);
            $table->string('land_use', 50);
            $table->integer('year');
            $table->integer('serial_number');
            $table->string('session_id', 100)->index();
            $table->string('user_id', 50)->nullable();
            $table->string('reserved_file_number', 100)->index();
            $table->enum('status', ['reserved', 'confirmed', 'released', 'expired'])->default('reserved');
            $table->timestamp('reserved_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Composite unique index to prevent duplicate reservations
            $table->unique(['prefix', 'land_use', 'year', 'serial_number', 'status'], 'unique_reservation');
            
            // Index for cleanup queries
            $table->index(['status', 'expires_at'], 'status_expires_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('file_number_reservations');
    }
};
