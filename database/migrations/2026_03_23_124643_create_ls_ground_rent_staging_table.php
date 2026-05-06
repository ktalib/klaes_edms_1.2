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
        Schema::connection('sqlsrv')->create('ls_ground_rent_staging', function (Blueprint $table) {
            $table->id();
            $table->string('prop_id', 50)->nullable()->index();
            $table->string('file_number', 100)->nullable()->index();
            $table->text('comment')->nullable();
            $table->decimal('amount', 18, 2)->nullable();
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
        Schema::connection('sqlsrv')->dropIfExists('ls_ground_rent_staging');
    }
};
