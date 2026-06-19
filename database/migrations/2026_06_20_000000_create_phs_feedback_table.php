<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feedback / complaints raised by PHS members about incomplete or wrong
     * transactions appearing in a property history search slip.
     */
    public function up()
    {
        Schema::connection('sqlsrv')->create('phs_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('phs_institution_id');
            $table->unsignedBigInteger('phs_member_id')->nullable();
            // incomplete_transaction | wrong_transaction | missing_record | other
            $table->string('category', 50);
            $table->string('file_number', 100)->nullable();
            $table->string('reference_no', 100)->nullable();
            $table->string('subject', 255)->nullable();
            $table->text('message');
            // open | in_review | resolved | closed
            $table->string('status', 20)->default('open');
            $table->text('admin_response')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('phs_institution_id');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('phs_feedback');
    }
};
