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
        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            $table->dateTime('approved_at')->nullable();
            $table->string('rofo_status')->default('pending');
            $table->dateTime('rofo_generated_at')->nullable();
            $table->integer('rofo_print_count')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'rofo_status', 'rofo_generated_at', 'rofo_print_count']);
        });
    }
};
