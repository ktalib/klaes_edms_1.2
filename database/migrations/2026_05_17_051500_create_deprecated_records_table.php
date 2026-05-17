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
        Schema::connection('sqlsrv')->create('deprecated_records', function (Blueprint $table) {
            $table->id();
            $table->integer('file_indexing_id')->nullable();
            $table->string('file_number', 100)->nullable();
            $table->string('file_title', 255)->nullable();
            $table->string('land_use_type', 100)->nullable();
            $table->string('plot_number', 255)->nullable();
            $table->string('district', 150)->nullable();
            $table->string('lga', 150)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('plot_size', 100)->nullable();
            $table->string('tp_no', 100)->nullable();
            $table->string('lpkn_no', 100)->nullable();
            $table->string('tracking_id', 150)->nullable();
            $table->string('original_holder', 255)->nullable();
            $table->string('current_holder', 255)->nullable();
            $table->string('parent_prop_id', 255)->nullable();
            $table->text('related_fileno')->nullable();
            $table->tinyInteger('has_transaction')->default(0);
            $table->string('workflow_type', 100)->nullable(); // Merger, Subdivision, Extension, CoP
            $table->string('decommissioned_by', 150)->nullable();
            $table->timestamp('decommissioned_at')->nullable();
            
            // Other useful metadata fields from file_indexings to preserve full fidelity
            $table->string('created_by', 150)->nullable();
            $table->string('updated_by', 150)->nullable();
            $table->string('serial_no', 100)->nullable();
            $table->string('batch_no', 100)->nullable();
            $table->string('workflow_status', 100)->nullable();
            $table->string('registry', 100)->nullable();
            $table->string('prop_id', 100)->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('residence_address', 255)->nullable();
            $table->string('general_registry', 100)->nullable();
            
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
        Schema::connection('sqlsrv')->dropIfExists('deprecated_records');
    }
};
