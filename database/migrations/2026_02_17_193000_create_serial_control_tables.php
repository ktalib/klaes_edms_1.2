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
        Schema::connection('sqlsrv')->create('dciv_serial_control', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 50)->index();
            $table->integer('year')->index();
            $table->integer('last_serial')->default(0);
            $table->boolean('is_initialized')->default(0);
            $table->timestamp('initialized_at')->nullable();
            $table->string('initialized_by', 255)->nullable();
            $table->boolean('is_locked')->default(0);
            $table->timestamps();

            // Ensure unique combination of prefix and year
            $table->unique(['prefix', 'year'], 'uniq_dciv_prefix_year');
        });

        Schema::connection('sqlsrv')->create('gkn_serial_control', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 50)->index();
            $table->integer('last_serial')->default(0);
            $table->boolean('is_initialized')->default(0);
            $table->timestamp('initialized_at')->nullable();
            $table->string('initialized_by', 255)->nullable();
            $table->boolean('is_locked')->default(0);
            $table->timestamps();

            // Ensure unique prefix
            $table->unique(['prefix'], 'uniq_gkn_prefix');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('dciv_serial_control');
        Schema::connection('sqlsrv')->dropIfExists('gkn_serial_control');
    }
};
