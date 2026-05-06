<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->create('land_rofo_security_paper_codes', function (Blueprint $table) {
            $table->id();
            $table->string('paper_code')->unique();
            $table->boolean('is_used')->default(false);
            $table->string('assigned_to_type')->nullable();
            $table->bigInteger('assigned_to_id')->nullable();
            $table->string('assigned_by')->nullable();
            $table->dateTime('assigned_at')->nullable();
            $table->timestamps();
        });

        // Seed 20 test records
        for ($i = 1; $i <= 20; $i++) {
            DB::connection('sqlsrv')->table('land_rofo_security_paper_codes')->insert([
                'paper_code' => str_pad($i, 6, '0', STR_PAD_LEFT),
                'is_used' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('land_rofo_security_paper_codes');
    }
};
