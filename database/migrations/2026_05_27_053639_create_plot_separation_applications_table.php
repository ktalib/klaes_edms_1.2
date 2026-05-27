<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->create('plot_separation_applications', function (Blueprint $table) {
            $table->id();
            $table->string('file_no', 100);
            $table->string('file_title', 500);
            $table->string('applicant_name', 255)->nullable();
            $table->string('plot_no', 100)->nullable();
            $table->string('house_no', 100)->nullable();
            $table->string('street_name', 255)->nullable();
            $table->string('district', 255)->nullable();
            $table->string('lga', 255)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('land_use', 100)->nullable();
            $table->string('residential_address', 500)->nullable();
            $table->string('correspondence_address', 500)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('occupation', 255)->nullable();
            $table->integer('num_plots')->default(1);
            $table->string('status', 50)->default('pending');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('captured_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('knupda_status', 50)->nullable();
            $table->decimal('knupda_fee', 18, 2)->nullable();
            $table->decimal('land_value', 18, 2)->nullable();
            $table->text('knupda_remarks')->nullable();
            $table->timestamp('application_generated_at')->nullable();
            $table->timestamp('recommendation_generated_at')->nullable();
            $table->string('site_plan')->nullable();
            $table->string('ownership_document')->nullable();
            $table->string('application_letter')->nullable();
            $table->string('means_of_id')->nullable();
            $table->string('tax_clearance')->nullable();
            $table->tinyInteger('is_deleted')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('plot_separation_applications');
    }
};
