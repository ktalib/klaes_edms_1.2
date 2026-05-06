<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates st_file_numbers table as the central registry for all ST file numbers
     * and their relationships, replacing the complex reservation system.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->create('st_file_numbers', function (Blueprint $table) {
            $table->id();
            
            // File number details
            $table->string('np_fileno', 50)->index()->comment('Primary file number (e.g., ST-RES-2025-1)');
            $table->string('fileno', 60)->unique()->comment('Unit file number (e.g., ST-RES-2025-1-001, or same as np_fileno for primary)');
            $table->string('mls_fileno', 50)->index()->comment('MLS file number (typically same as np_fileno)');
            
            // Land use information
            $table->string('land_use', 50)->comment('Full name: Residential, Commercial, Industrial, Mixed');
            $table->string('land_use_code', 10)->index()->comment('RES, COM, IND, MIXED');
            $table->integer('serial_no')->comment('Sequential number within land use/year');
            $table->integer('unit_sequence')->nullable()->comment('NULL for primary, 001+ for units');
            $table->integer('year')->index()->comment('Year of file number');
            
            // File type and relationships
            $table->enum('file_no_type', ['PRIMARY', 'SUA', 'PUA'])->index()->comment('File number type');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('References id of parent record for PUA applications');
            $table->unsignedInteger('mother_application_id')->nullable()->index()->comment('FK to mother_applications');
            $table->unsignedInteger('subapplication_id')->nullable()->index()->comment('FK to subapplications');
            
            // Status and timing
            $table->enum('status', ['RESERVED', 'ACTIVE', 'USED', 'CANCELLED'])->default('RESERVED')->index();
            $table->timestamp('reserved_at')->comment('When file number was generated/reserved');
            $table->timestamp('expires_at')->nullable()->index()->comment('When reservation expires (24 hours for reservations)');
            $table->timestamp('used_at')->nullable()->comment('When application was submitted');
            
            // Tracking and audit
            $table->string('tra', 20)->nullable()->comment('Transaction Reference (Tracking ID)');
            
            // Applicant information (stored for quick reference)
            $table->enum('applicant_type', ['Individual', 'Corporate', 'Multiple'])->nullable();
            $table->string('applicant_title', 20)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('surname', 100)->nullable();
            $table->string('corporate_name', 200)->nullable();
            $table->string('rc_number', 50)->nullable();
            $table->text('multiple_owners_names')->nullable()->comment('JSON array of multiple owner names');
            
            // Audit trail
            $table->unsignedBigInteger('created_by')->nullable()->comment('User who created this file number');
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('parent_id')->references('id')->on('st_file_numbers')->onDelete('no action');
            $table->foreign('mother_application_id')->references('id')->on('mother_applications')->onDelete('set null');
            $table->foreign('subapplication_id')->references('id')->on('subapplications')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            
            // Performance indexes
            $table->index(['land_use_code', 'year', 'serial_no'], 'idx_land_use_serial');
            $table->index(['file_no_type', 'status'], 'idx_type_status');
            $table->index(['status', 'expires_at'], 'idx_status_expiry');
            $table->index(['np_fileno', 'file_no_type'], 'idx_np_type');
            $table->index('reserved_at');
            $table->index('tra');
        });
        
        // Add comment to table
        DB::connection('sqlsrv')->statement("
            EXEC sys.sp_addextendedproperty 
                @name = N'MS_Description', 
                @value = N'Central registry for all ST file numbers and their relationships. Replaces complex reservation system with unified approach.',
                @level0type = N'SCHEMA', @level0name = N'dbo',
                @level1type = N'TABLE', @level1name = N'st_file_numbers'
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('st_file_numbers');
    }
};