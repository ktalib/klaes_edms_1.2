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
        if (!Schema::connection('sqlsrv')->hasTable('file_indexing_links')) {
            Schema::connection('sqlsrv')->create('file_indexing_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('main_application_id')->nullable();
                $table->unsignedBigInteger('subapplication_id')->nullable();
                $table->unsignedBigInteger('recertification_application_id')->nullable();
                $table->string('tracking_id', 255)->nullable();
                $table->string('st_fillno', 255)->nullable();
                $table->string('serial_no', 255)->nullable();
                $table->string('batch_no', 255)->nullable();
                $table->string('st_batch_no', 255)->nullable();
                $table->string('shelf_location', 255)->nullable();
                $table->unsignedBigInteger('shelf_label_id')->nullable();
                $table->string('sys_batch_no', 255)->nullable();
                $table->string('group_no', 255)->nullable();
                $table->string('file_number', 255)->nullable();
                $table->string('file_title', 255)->nullable();
                $table->string('land_use_type', 255)->nullable();
                $table->string('plot_number', 255)->nullable();
                $table->string('tp_no', 255)->nullable();
                $table->string('lpkn_no', 255)->nullable();
                $table->string('location', 255)->nullable();
                $table->string('district', 255)->nullable();
                $table->string('registry', 255)->nullable();
                $table->string('physical_registry', 255)->nullable();
                $table->string('general_registry', 255)->nullable();
                $table->string('lga', 255)->nullable();
                $table->text('property_description')->nullable();
                $table->string('file_type', 255)->nullable();
                $table->date('dob')->nullable();
                $table->string('nin', 20)->nullable();
                $table->string('tin', 50)->nullable();
                $table->string('rc_no', 100)->nullable();
                $table->text('residence_address')->nullable();
                $table->string('country_code', 10)->nullable();
                $table->string('phone', 20)->nullable();
                $table->boolean('has_cofo')->default(false);
                $table->boolean('is_merged')->default(false);
                $table->boolean('has_transaction')->default(false);
                $table->boolean('is_problematic')->default(false);
                $table->boolean('is_co_owned_plot')->default(false);
                $table->string('test_control', 25)->nullable();
                $table->boolean('is_updated')->default(false);
                $table->string('workflow_status', 255)->nullable();
                $table->boolean('has_qc_issues')->default(false);
                $table->string('created_by', 255)->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->string('related_fileno', 255)->nullable();
                $table->string('registry_batch_no', 255)->nullable();
                $table->boolean('batch_generated')->default(false);
                $table->unsignedBigInteger('last_batch_id')->nullable();
                $table->datetime('batch_generated_at')->nullable();
                $table->string('batch_generated_by', 255)->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->datetime('deleted_at')->nullable();
                $table->timestamps();

                // Index for faster lookups
                $table->index('file_number');
                $table->index('tracking_id');
                $table->index('physical_registry');
                $table->index('general_registry');
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
        Schema::connection('sqlsrv')->dropIfExists('file_indexing_links');
    }
};
