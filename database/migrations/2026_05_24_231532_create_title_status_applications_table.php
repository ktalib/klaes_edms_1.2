<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->create('title_status_applications', function (Blueprint $table) {
            $table->id();
            $table->string('url', 50)->default('land');
            $table->string('file_no', 255);
            $table->string('file_title', 500)->nullable();
            $table->string('applicant_name', 255);
            $table->string('title_type', 255)->nullable();
            $table->string('title_no', 255)->nullable();
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
            $table->date('date_of_issue')->nullable();
            $table->date('date_of_expiry')->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('captured_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->tinyInteger('is_deleted')->nullable()->default(0);
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
