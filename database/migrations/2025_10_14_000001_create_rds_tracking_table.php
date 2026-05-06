<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Use SQL Server connection
        $connection = 'sqlsrv';
        
        Schema::connection($connection)->create('rds_tracking', function (Blueprint $table) {
            $table->id();
            
            // Instrument reference
            $table->unsignedBigInteger('instrument_id')->index();
            $table->string('stm_ref', 50)->index();
            $table->string('rds_reference', 50)->unique();
            
            // Instrument details (denormalized for quick access)
            $table->string('instrument_type', 100)->nullable();
            $table->text('grantor')->nullable();
            $table->text('grantee')->nullable();
            $table->string('file_number', 100)->nullable()->index();
            $table->datetime('registration_date')->nullable();
            
            // RDS metadata
            $table->string('status', 20)->default('generated'); // generated, cancelled
            $table->integer('print_count')->default(0);
            
            // Generation tracking
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->datetime('generated_at')->nullable();
            
            // Print tracking
            $table->unsignedBigInteger('last_printed_by')->nullable();
            $table->datetime('last_printed_at')->nullable();
            
            // Cancellation tracking
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->datetime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            
            // Additional metadata
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // For future extensibility
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('status');
            $table->index('generated_at');
            $table->index('generated_by');
        });
        
        // Note: Foreign key constraint skipped - can be added manually after verifying
        // the primary key structure of registered_instruments table
        // To add manually, run:
        // ALTER TABLE rds_tracking ADD CONSTRAINT fk_rds_instrument
        // FOREIGN KEY (instrument_id) REFERENCES registered_instruments(id) ON DELETE CASCADE
        
        // Create index for better query performance
        DB::connection($connection)->statement('
            CREATE NONCLUSTERED INDEX idx_rds_composite 
            ON rds_tracking (instrument_id, status, generated_at)
            INCLUDE (rds_reference, stm_ref)
        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $connection = 'sqlsrv';
        
        // Drop foreign key constraint first
        DB::connection($connection)->statement('
            ALTER TABLE rds_tracking
            DROP CONSTRAINT IF EXISTS fk_rds_instrument
        ');
        
        Schema::connection($connection)->dropIfExists('rds_tracking');
    }
};
