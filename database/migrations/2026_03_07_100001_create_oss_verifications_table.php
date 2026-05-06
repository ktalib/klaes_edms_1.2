<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up()
    {
        Schema::connection('sqlsrv')->create('oss_verifications', function (Blueprint $table) {
            $table->id();

            // Link back to instrument_capture record
            $table->unsignedBigInteger('instrument_capture_id')->nullable();

            // ── Part A – Applicant Details ──
            $table->string('applicant_name', 255)->nullable();
            $table->string('applicant_address', 500)->nullable();
            $table->string('applicant_phone', 50)->nullable();
            $table->string('applicant_email', 255)->nullable();
            $table->string('id_type', 50)->nullable(); // National I.D., Voters Card, Driving Licence, International Passport, Others

            // ── Part B – OP Details ──
            $table->string('original_allottee', 255)->nullable();
            $table->string('op_number', 100)->nullable();
            $table->string('plot_no', 100)->nullable();
            $table->string('plan_no', 100)->nullable();
            $table->string('location', 500)->nullable();

            // ── Official Use Only ──
            $table->string('recommendation', 30)->nullable(); // Recommended, Not Recommended
            $table->string('chairman_name', 255)->nullable();

            // ── Audit ──
            $table->unsignedBigInteger('captured_by')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('oss_verifications');
    }
};
