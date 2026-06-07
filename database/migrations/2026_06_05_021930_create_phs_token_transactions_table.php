<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->create('phs_token_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('phs_institution_id');
            $table->unsignedBigInteger('phs_member_id')->nullable();
            $table->string('type', 30); // purchase | search_debit | bonus | adjustment
            $table->integer('tokens'); // signed: positive credit, negative debit
            $table->integer('balance_after')->default(0);
            $table->string('package_name', 100)->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('payment_method', 20)->nullable(); // online | invoice
            $table->string('status', 20)->default('completed'); // completed | pending
            $table->string('reference_no', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('approved_by')->nullable(); // staff user id (invoice approval)
            $table->timestamps();

            $table->index('phs_institution_id');
            $table->index('type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('phs_token_transactions');
    }
};
