<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('consent_applications', function (Blueprint $table) {
            $table->id();
            $table->string('file_number')->index();
            $table->enum('consent_type', ['Assignment', 'Gift', 'Mortgage']);

            // Applicant Info (From)
            $table->string('applicant_name');
            $table->text('applicant_address')->nullable();

            // Party Info (To - Assignee/Donee/Mortgagee)
            $table->string('party_name');
            $table->text('party_address')->nullable();

            // Financials
            $table->string('consideration')->nullable(); // Can be "AS A GIFT" or numeric amount as string

            // Metadata
            $table->date('application_date')->nullable(); // Date on the applicant's letter
            $table->string('status')->default('Generated');
            $table->integer('print_count')->default(0);

            $table->foreignId('user_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('consent_applications');
    }
};
