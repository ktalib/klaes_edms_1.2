<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up()
    {
        Schema::connection('sqlsrv')->create('oss_change_of_ownership', function (Blueprint $table) {
            $table->id();

            // Link back to instrument_capture record
            $table->unsignedBigInteger('instrument_capture_id')->nullable();

            // ── Section 1 – OP Details ──
            $table->string('op_number', 100)->nullable();
            $table->string('location', 500)->nullable();
            $table->string('plot_no', 100)->nullable();
            $table->string('plan_no', 100)->nullable();
            $table->date('date_of_issuance')->nullable();

            // ── Section 2 – Original Allottee ──
            $table->string('original_name', 255)->nullable();
            $table->string('original_address', 500)->nullable();
            $table->string('original_phone', 50)->nullable();

            // ── Section 3 – Current Owner ──
            $table->string('current_name', 255)->nullable();
            $table->string('current_address', 500)->nullable();
            $table->string('current_phone', 50)->nullable();

            // ── Section 4 – How ownership was obtained ──
            $table->string('ownership_method', 50)->nullable(); // Direct, Resettlement, Gift, Purchase, Inheritance

            // ── Audit ──
            $table->unsignedBigInteger('captured_by')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('oss_change_of_ownership');
    }
};
