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
        Schema::connection('sqlsrv')->create('security_codes', function (Blueprint $table) {
            $table->id();
            $table->string('serial_no')->nullable();
            $table->string('code', 9)->unique();
            $table->boolean('is_used')->default(0);
            $table->string('assigned_to')->nullable();
            $table->string('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->string('security_paper_code')->nullable();
            $table->string('used_security_paper_code')->nullable();
            $table->string('file_number')->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('document_type')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('security_codes');
    }
};
