<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates customers table to store customer information including
     * personal/corporate details, contact information, and audit trails.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->create('customers', function (Blueprint $table) {
            $table->id();
            
            // Customer Type & Status
            $table->string('customer_type')->index();
            $table->string('status')->index();
            
            // Customer Information
            $table->string('customer_name', 255);
            
            // Contact Information
            $table->string('email', 255)->unique()->nullable();
            $table->string('phone', 20)->nullable();
 
            
            // Address Information
            // Property Address Information/Location
            $table->string('property_address', 255)->nullable();

            
            // Physical Address Information
            $table->string('physical_address', 255)->nullable();

            
            // Metadata
            $table->text('notes')->nullable();
            $table->string('customer_code', 50)->unique()->nullable()->comment('Generated customer code for reference');
            
            // Audit Trail
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['customer_type', 'status']);
            $table->index(['created_at']);
            $table->index(['email']);
            $table->index(['phone']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('customers');
    }
};
