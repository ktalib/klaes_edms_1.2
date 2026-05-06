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
        if (!Schema::connection('sqlsrv')->hasTable('mls_serial_control')) {
            Schema::connection('sqlsrv')->create('mls_serial_control', function (Blueprint $table) {
                $table->id();
                $table->string('land_use', 50)->index();
                $table->integer('year')->index();
                $table->integer('last_serial')->default(0);
                $table->boolean('is_initialized')->default(0);
                $table->timestamp('initialized_at')->nullable();
                $table->string('initialized_by', 255)->nullable();
                $table->boolean('is_locked')->default(0);
                $table->timestamps();

                // Ensure unique combination of land_use and year
                $table->unique(['land_use', 'year'], 'uniq_land_use_year');
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
        Schema::connection('sqlsrv')->dropIfExists('mls_serial_control');
    }
};
