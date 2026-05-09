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
        Schema::connection('sqlsrv')->create('streets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });

        // Seed some default streets
        $streets = [
            'Ahmadu Bello Way',
            'Zaria Road',
            'Airport Road',
            'Hadejia Road',
            'Ibrahim Taiwo Road',
            'Murtala Mohammed Way',
            'Bompai Road',
            'Sultan Road'
        ];

        foreach ($streets as $street) {
            DB::connection('sqlsrv')->table('streets')->insert([
                'name' => $street,
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
        Schema::connection('sqlsrv')->dropIfExists('streets');
    }
};
