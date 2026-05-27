<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('title_status_applications');

        Schema::connection('sqlsrv')->create('title_status_applications', function (Blueprint $table) {
            $table->id();

            // Module context
            $table->string('url', 50)->default('land');

            // Title status type (one of the 5 types)
            $table->string('title_type', 100);

            // Source file reference
            $table->string('source_table', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            // File details (copied from source)
            $table->string('file_no', 255);
            $table->string('file_title', 500)->nullable();
            $table->string('applicant_name', 255)->nullable();
            $table->string('plot_no', 100)->nullable();
            $table->string('house_no', 100)->nullable();
            $table->string('street_name', 255)->nullable();
            $table->string('district', 255)->nullable();
            $table->string('lga', 255)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('location', 2000)->nullable();
            $table->string('land_use', 255)->nullable();

            // Title document
            $table->string('title_no', 255)->nullable();
            $table->date('date_of_issue')->nullable();
            $table->date('date_of_expiry')->nullable();

            // Authority
            $table->string('authority', 255)->nullable();
            $table->string('authority_reference', 500)->nullable();

            // Remark (auto-generated, editable)
            $table->text('remark')->nullable();

            // Workflow
            $table->string('status', 30)->default('pending');
            $table->unsignedBigInteger('captured_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            // Soft delete
            $table->tinyInteger('is_deleted')->default(0)->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamp('deleted_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('title_status_applications');
    }
};
