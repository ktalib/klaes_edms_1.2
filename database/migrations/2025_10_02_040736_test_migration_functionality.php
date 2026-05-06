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
        if (!Schema::connection('sqlsrv')->hasTable('test_migrations_table')) {
            Schema::connection('sqlsrv')->create('test_migrations_table', function (Blueprint $table) {
                $table->id();
                $table->string('test_column')->nullable();
                $table->boolean('is_test')->default(true);
                $table->timestamps();
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
        Schema::connection('sqlsrv')->dropIfExists('test_migrations_table');
    }
};
