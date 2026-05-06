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
        Schema::connection('sqlsrv')->create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('office_code', 20)->unique()->comment('Unique office code identifier (e.g., OFF-001)');
            $table->string('office_name', 255)->comment('Full office name');
            $table->string('office_abbreviation', 10)->comment('Office abbreviation (e.g., RCP, CCU)');
            $table->string('department', 100)->comment('Department/division the office belongs to');
            $table->boolean('is_active')->default(true)->comment('Whether the office is currently active');
            $table->timestamps();
            
            // Indexes for better performance
            $table->index('office_code');
            $table->index('is_active');
            $table->index('department');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('offices');
    }
};
