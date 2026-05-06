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
        Schema::connection('sqlsrv')->create('land_rofo_serial_no', function (Blueprint $table) {
            $table->id();
            $table->string('serial_no', 6)->unique();
            $table->boolean('is_used')->default(false);
            $table->bigInteger('assigned_to')->nullable(); // Foreign key to land_recommendations.id
            $table->bigInteger('assigned_by')->nullable(); // Foreign key to users.id
            $table->timestamp('assigned_at')->nullable();
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
        Schema::connection('sqlsrv')->dropIfExists('land_rofo_serial_no');
    }
};
