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
        Schema::connection('sqlsrv')->create('conversion_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mls_file_no_id')->nullable()->index();
            $table->string('tracking_id', 50)->nullable()->index();
            $table->string('full_file_number', 100)->index();
            $table->string('generated_by', 255);
            $table->text('notes')->nullable();
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
        Schema::connection('sqlsrv')->dropIfExists('conversion_applications');
    }
};
