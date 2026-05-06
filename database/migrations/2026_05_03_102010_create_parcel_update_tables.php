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
        // 1. Plot Subdivision Applications
        Schema::connection('sqlsrv')->create('plot_subdivision_applications', function (Blueprint $table) {
            $table->id();
            $table->string('file_no', 100);
            $table->string('file_title', 500)->nullable();
            $table->string('applicant_name', 255)->nullable();
            $table->string('plot_no', 100)->nullable();
            $table->string('house_no', 100)->nullable();
            $table->string('street_name', 255)->nullable();
            $table->string('district', 255)->nullable();
            $table->string('lga', 255)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('residential_address', 2000)->nullable();
            $table->string('correspondence_address', 2000)->nullable();
            $table->string('nationality', 255)->nullable();
            $table->string('occupation', 255)->nullable();
            $table->string('land_use', 255)->nullable();
            $table->integer('num_plots')->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('captured_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // 2. Plot Merger Applications
        Schema::connection('sqlsrv')->create('plot_merger_applications', function (Blueprint $table) {
            $table->id();
            $table->string('temp_file_no', 100)->nullable();
            $table->string('file_no', 100);
            $table->string('file_title', 500)->nullable();
            $table->string('applicant_name', 255)->nullable();
            $table->string('plot_no', 100)->nullable();
            $table->string('house_no', 100)->nullable();
            $table->string('street_name', 255)->nullable();
            $table->string('district', 255)->nullable();
            $table->string('lga', 255)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('residential_address', 2000)->nullable();
            $table->string('correspondence_address', 2000)->nullable();
            $table->string('nationality', 255)->nullable();
            $table->string('occupation', 255)->nullable();
            $table->string('land_use', 255)->nullable();
            $table->integer('num_plots')->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('captured_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // 3. Plot Extension Applications
        Schema::connection('sqlsrv')->create('plot_extension_applications', function (Blueprint $table) {
            $table->id();
            $table->string('file_no', 100);
            $table->string('file_title', 500)->nullable();
            $table->string('applicant_name', 255)->nullable();
            $table->string('plot_no', 100)->nullable();
            $table->string('house_no', 100)->nullable();
            $table->string('street_name', 255)->nullable();
            $table->string('district', 255)->nullable();
            $table->string('lga', 255)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('residential_address', 2000)->nullable();
            $table->string('correspondence_address', 2000)->nullable();
            $table->string('nationality', 255)->nullable();
            $table->string('occupation', 255)->nullable();
            $table->string('land_use', 255)->nullable();
            $table->string('location', 2000)->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('captured_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // 4. Shared Plot Application Sizes
        Schema::connection('sqlsrv')->create('plot_application_sizes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->string('application_type', 50); // subdivision, merger, extension
            $table->string('plot_number', 50)->nullable();
            $table->decimal('plot_size', 18, 2)->nullable();
            $table->string('type', 50)->nullable(); // e.g., 'subdivision_fragment', 'merger_source'
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
        Schema::connection('sqlsrv')->dropIfExists('plot_application_sizes');
        Schema::connection('sqlsrv')->dropIfExists('plot_extension_applications');
        Schema::connection('sqlsrv')->dropIfExists('plot_merger_applications');
        Schema::connection('sqlsrv')->dropIfExists('plot_subdivision_applications');
    }
};
