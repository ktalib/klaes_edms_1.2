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
        Schema::connection('sqlsrv')->create('phs_institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('type', 30)->default('bank'); // bank | law_firm | corporate
            $table->string('email', 255)->unique();
            $table->string('phone', 50)->nullable();
            $table->integer('token_balance')->default(0);
            $table->string('primary_color', 20)->default('#2563eb');
            $table->string('secondary_color', 20)->default('#7c3aed');
            $table->string('logo_path', 255)->nullable();
            $table->string('banner_path', 255)->nullable();
            $table->string('status', 20)->default('active'); // active | suspended
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('phs_institutions');
    }
};
