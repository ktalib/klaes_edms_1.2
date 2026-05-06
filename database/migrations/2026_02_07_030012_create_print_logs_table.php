<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('print_logs', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->index(); // Batch No or Tracking ID/File No
            $table->string('document_type'); // 'Commissioning Sheet' or 'Conversion Application'
            $table->string('print_type'); // 'Batch' or 'Individual'
            $table->string('status'); // 'Original' or 'Duplicate'
            $table->foreignId('user_id')->nullable(); // Who printed it
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('print_logs');
    }
};
