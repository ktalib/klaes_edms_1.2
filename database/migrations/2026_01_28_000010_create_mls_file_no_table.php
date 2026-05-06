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
        if (!Schema::connection('sqlsrv')->hasTable('mls_file_no')) {
            Schema::connection('sqlsrv')->create('mls_file_no', function (Blueprint $table) {
                $table->id();
                $table->string('land_use', 50)->index();
                $table->integer('year')->index();
                $table->integer('serial_number');
                $table->string('full_file_number', 100)->unique()->index();
                $table->string('file_name', 500)->nullable();
                $table->string('plot_no', 100)->nullable();
                $table->string('tp_no', 100)->nullable();
                $table->text('location')->nullable();
                $table->string('tracking_id', 50)->nullable();
                $table->string('file_option', 50)->nullable(); // normal, extension, temporary, etc.
                $table->string('created_by', 255);
                $table->timestamps();
                $table->boolean('is_deleted')->default(0);

                // Composite index for efficient lookups
                $table->index(['land_use', 'year', 'serial_number'], 'idx_land_use_year_serial');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('mls_file_no');
    }
};
