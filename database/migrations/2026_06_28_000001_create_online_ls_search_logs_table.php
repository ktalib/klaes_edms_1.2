<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('online_ls_search_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('online_ls_user_id');
            $table->string('file_number', 100)->nullable();
            $table->json('search_params')->nullable();
            $table->unsignedInteger('results_count')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('online_ls_user_id');
            $table->index('file_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('online_ls_search_logs');
    }
};
