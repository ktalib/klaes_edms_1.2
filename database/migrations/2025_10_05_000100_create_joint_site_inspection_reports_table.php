<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('joint_site_inspection_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->date('inspection_date')->nullable();
            $table->string('lkn_number')->nullable();
            $table->string('applicant_name')->nullable();
            $table->string('location')->nullable();
            $table->string('plot_number')->nullable();
            $table->string('scheme_number')->nullable();
            $table->boolean('available_on_ground')->default(false);
            $table->text('boundary_description')->nullable();
            $table->integer('sections_count')->nullable();
            $table->string('road_reservation')->nullable();
            $table->string('prevailing_land_use')->nullable();
            $table->string('applied_land_use')->nullable();
            $table->json('shared_utilities')->nullable();
            $table->string('compliance_status')->nullable();
            $table->boolean('has_additional_observations')->default(false);
            $table->text('additional_observations')->nullable();
            $table->string('inspection_officer')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('application_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('joint_site_inspection_reports');
    }
};
