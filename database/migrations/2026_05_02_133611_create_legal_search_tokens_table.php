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
        Schema::connection('sqlsrv')->create('legal_search_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 20)->unique();
            $table->string('file_number', 50);
            $table->string('applicant_name', 255);
            $table->text('property_location')->nullable();
            $table->decimal('amount_paid', 18, 2);
            $table->string('receipt_number', 50);
            $table->date('date_paid');
            $table->boolean('is_used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('file_number');
            $table->index('token');
            $table->index('is_used');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('legal_search_tokens');
    }
};
