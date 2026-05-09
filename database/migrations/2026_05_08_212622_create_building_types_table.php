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
        Schema::connection('sqlsrv')->create('building_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });

        // Seed default values
        $types = [
            'Bungalow',
            'Duplex',
            'Terrace',
            'Semi Detached',
            'Mansion',
            'High rise',
            'Mansionette'
        ];

        foreach ($types as $type) {
            DB::connection('sqlsrv')->table('building_types')->insert([
                'name' => $type,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now()
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
        Schema::connection('sqlsrv')->dropIfExists('building_types');
    }
};
