<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->create('oss_applications', function (Blueprint $table) {
            $table->id();

            // ── Application type: residential | commercial | industrial ──
            $table->string('application_type', 30);

            // ── Common fields across all three types ──
            $table->string('applicant_name', 255)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('state_of_origin', 100)->nullable();
            $table->string('lga', 100)->nullable();

            // ── Property / Land Details ──
            $table->string('file_no', 100)->nullable();
            $table->string('plot_no', 100)->nullable();
            $table->string('plan_no', 100)->nullable();
            $table->string('location', 500)->nullable();
            $table->string('district', 255)->nullable();
            $table->string('land_use', 100)->nullable();
            $table->string('land_area', 100)->nullable();
            $table->string('property_description', 1000)->nullable();

            // ── Purpose / Nature of Application ──
            $table->string('purpose', 500)->nullable();
            $table->string('nature_of_development', 500)->nullable();

            // ── Financial ──
            $table->decimal('proposed_dev_value', 18, 2)->nullable();
            $table->decimal('annual_ground_rent', 18, 2)->nullable();
            $table->decimal('development_charge', 18, 2)->nullable();
            $table->decimal('survey_processing_charges', 18, 2)->nullable();
            $table->string('term_years', 20)->nullable();

            // ── Commercial-specific ──
            $table->string('business_name', 255)->nullable();
            $table->string('business_type', 255)->nullable();
            $table->string('rc_number', 100)->nullable();

            // ── Industrial-specific ──
            $table->string('industry_type', 255)->nullable();
            $table->string('product_type', 500)->nullable();
            $table->string('environmental_clearance', 100)->nullable();

            // ── Status & Workflow ──
            $table->string('status', 30)->default('pending');   // pending, processing, approved, rejected
            $table->text('remarks')->nullable();

            // ── Audit ──
            $table->unsignedBigInteger('captured_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

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
        Schema::connection('sqlsrv')->dropIfExists('oss_applications');
    }
};
