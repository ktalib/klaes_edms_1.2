<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds entity_id foreign key column to customers table to establish
     * one-to-many relationship with entities table (1 entity : many customers).
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->table('customers', function (Blueprint $table) {
            // Add entity_id column as foreign key
            $table->unsignedBigInteger('entity_id')->nullable()->after('id')->comment('Foreign key to entities table');

            // Add foreign key constraint
            $table->foreign('entity_id')
                ->references('id')
                ->on('entities')
                ->onDelete('restrict')
                ->onUpdate('cascade');

            // Add index for better query performance
            $table->index('entity_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('customers', function (Blueprint $table) {
            // Drop foreign key constraint
            $table->dropForeign(['entity_id']);

            // Drop the column
            $table->dropColumn('entity_id');
        });
    }
};
