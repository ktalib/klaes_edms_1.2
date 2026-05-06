<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->create('change_of_purpose_applications', function (Blueprint $table) {
            $table->bigIncrements('id');

            // File information
            $table->string('file_no', 255)->nullable();          // current (old) file number
            $table->string('land_use', 255)->nullable();         // current land use (auto-detected)
            $table->string('purpose', 50)->nullable();           // new purpose code (e.g. COM, RES)
            $table->string('plot_no', 100)->nullable();
            $table->string('plan_no', 100)->nullable();
            $table->string('location', 2000)->nullable();

            // Applicant information
            $table->string('applicant_name', 255);
            $table->string('phone', 50)->nullable();
            $table->string('nationality', 255)->nullable();
            $table->string('occupation', 255)->nullable();
            $table->text('residential_address')->nullable();
            $table->text('correspondence_address')->nullable();

            // Workflow
            $table->string('status', 50)->default('pending');    // pending | processing | approved | rejected | commissioned
            $table->text('remarks')->nullable();

            // Audit
            $table->unsignedBigInteger('captured_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('change_of_purpose_applications');
    }
};
