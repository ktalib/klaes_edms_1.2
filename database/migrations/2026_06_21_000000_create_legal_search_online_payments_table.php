<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_search_online_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 60)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('email', 255);
            $table->string('file_number', 100)->nullable();
            $table->json('search_params')->nullable();
            $table->unsignedInteger('amount')->default(100000); // in kobo (₦1,000)
            $table->enum('status', ['pending', 'paid', 'failed'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_search_online_payments');
    }
};
