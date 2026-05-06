<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates entities table to store unique owners (individuals, companies, or multiple owners).
     * This table is linked to customers through a one-to-many relationship.
     *
     * @return void
     */
    public function up()
    {
        // Skip if table already exists
        if (Schema::connection('sqlsrv')->hasTable('entities')) {
            return;
        }

        Schema::connection('sqlsrv')->create('entities', function (Blueprint $table) {
            $table->id();
            
            // Entity Classification
            $table->string('entity_type', 50)->index()->comment('Individual / Corporate / Multiple Owners');
            $table->string('entity_name', 255)->index()->comment('Full legal name of entity');
            
            // Media Storage
            $table->string('passport_photo', 255)->nullable()->comment('File path for individual passport');
            $table->string('company_logo', 255)->nullable()->comment('File path for corporate logo');
            
            // Audit Trail
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            // Indexes for performance
            $table->index(['entity_type', 'entity_name']);
            $table->index(['entity_name']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('entities');
    }
};
