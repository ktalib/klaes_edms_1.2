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
        if (!Schema::connection('sqlsrv')->hasTable('instrument_capture')) {
            Schema::connection('sqlsrv')->create('instrument_capture', function (Blueprint $table) {
                $table->id();

                // File Identifiers
                $table->string('instrument_type')->index();
                $table->string('mlsFNo')->nullable()->index();
                $table->string('kangisFileNo')->nullable()->index();
                $table->string('NewKANGISFileno')->nullable()->index();
                $table->string('temp_fileno')->nullable()->index();
                $table->unsignedBigInteger('prop_id')->nullable()->index()->comment('Linked to PropID_Master or legacy tables');

                // Flags
                $table->boolean('is_temporary_file')->default(false);
                $table->boolean('is_temporary_reg')->default(false);

                // Registration Particulars
                $table->string('root_reg_number')->nullable();
                $table->string('reg_no')->nullable()->index()->comment('Maps to instrumentNo');
                $table->string('registration_number')->nullable()->index()->comment('Generated particulars e.g. 5/5/1');
                $table->integer('volume_no')->nullable();
                $table->integer('page_no')->nullable();
                $table->integer('serial_no')->nullable();
                $table->date('reg_date')->nullable();

                // Standardized Parties
                // Party 1 (Grantor, Assignor, etc.)
                $table->string('party_1_name')->nullable();
                $table->text('party_1_address')->nullable();
                $table->string('party_1_phone')->nullable();
                $table->string('party_1_nationality')->nullable();
                $table->string('party_1_id_type')->nullable();
                $table->string('party_1_id_number')->nullable();

                // Party 2 (Grantee, Assignee, etc.)
                $table->string('party_2_name')->nullable();
                $table->text('party_2_address')->nullable();
                $table->string('party_2_phone')->nullable();
                $table->string('party_2_nationality')->nullable();
                $table->string('party_2_id_type')->nullable();
                $table->string('party_2_id_number')->nullable();

                // Party 3 (Co-Mortgagor, Co-Surrenderor, etc.)
                $table->string('party_3_name')->nullable();
                $table->text('party_3_address')->nullable();
                $table->string('party_3_phone')->nullable();
                $table->string('party_3_nationality')->nullable();
                $table->string('party_3_id_type')->nullable();
                $table->string('party_3_id_number')->nullable();

                // Solicitor
                $table->string('solicitor_name')->nullable();
                $table->text('solicitor_address')->nullable();

                // Property Details
                $table->text('property_description')->nullable();
                $table->string('property_location')->nullable();
                $table->string('plot_number')->nullable();
                $table->string('plot_size')->nullable();
                $table->string('survey_plan_no')->nullable();
                $table->string('lga')->nullable();
                $table->string('district')->nullable();
                $table->string('surveyor_name')->nullable();
                $table->string('land_use')->nullable();

                // Instrument Specific Fields (Consolidated)
                $table->date('entry_date')->nullable();
                $table->date('instrument_date')->nullable(); // dateOfExecution
                $table->string('duration')->nullable(); // leaseTerm, assignmentTerm
                $table->date('start_date')->nullable(); // leaseBegins
                $table->date('end_date')->nullable(); // leaseExpires
                $table->string('annual_rent')->nullable();
                $table->string('consideration_amount')->nullable();
                $table->string('compensation_amount')->nullable();
                $table->string('bank_name')->nullable();
                $table->date('mortgage_date')->nullable();
                $table->date('governor_consent_date')->nullable();
                $table->date('chief_magistrate_date')->nullable();
                $table->date('cofo_date')->nullable();
                $table->string('cofo_reg_particulars')->nullable();
                $table->integer('number_of_plots')->nullable();
                $table->string('original_plot_size')->nullable();
                $table->date('merger_date')->nullable();
                $table->text('merged_entities')->nullable();
                $table->string('new_entity_name')->nullable();
                $table->string('surrender_reason')->nullable();
                $table->string('release_reg_particulars')->nullable();
                $table->string('original_instrument_reg')->nullable();
                $table->string('deceased_name')->nullable();
                $table->date('date_of_death')->nullable();
                $table->string('will_reference')->nullable();
                $table->text('encumbrances')->nullable();
                $table->text('supporting_docs')->nullable();
                $table->string('blockchain_hash')->nullable();
                $table->string('registrar_name')->nullable();
                $table->string('registrar_signature')->nullable();

                // Metadata
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                // Foreign keys if needed, though often loose in this system
                // $table->foreign('created_by')->references('id')->on('users');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('instrument_capture');
    }
};
