<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * neighborhood_characteristics
     */
    public function up()
    {
        Schema::create('valuation_reports', function (Blueprint $table) {
            $table->id();
            $table->string('file_number')->index();

            // 1 & 2
            $table->date('inspection_date')->nullable();
            $table->string('valuation_purpose')->nullable();

            // 3. OWNER / CLIENT DETAILS
            $table->string('full_name')->nullable();
            $table->text('address')->nullable();
            $table->string('property_no')->nullable();
            $table->string('street_name')->nullable();
            $table->string('estate_quarters')->nullable();
            $table->string('town_city')->nullable();
            $table->string('lga')->nullable();

            // 4. PROPERTY DESCRIPTION
            $table->string('property_type')->nullable();
            $table->string('num_blocks')->nullable();
            $table->string('storey_height')->nullable();
            $table->string('bedroom_units')->nullable();
            $table->text('ancillary_bldgs')->nullable();
            $table->string('current_use')->nullable();

            // 5. CONSTRUCTION DETAILS
            $table->string('foundation_type')->nullable();
            $table->string('foundation_stage')->nullable();
            $table->string('floor_type')->nullable();
            $table->string('floor_finish')->nullable();
            $table->string('walls_type')->nullable();
            $table->string('walls_finish')->nullable();
            $table->string('roof_type')->nullable();
            $table->string('roof_material')->nullable();
            $table->string('ceiling_detail')->nullable();
            $table->string('ext_doors')->nullable();
            $table->string('window_type')->nullable();
            $table->string('burglary_proof')->nullable();

            // 6. SERVICES & UTILITIES
            $table->text('toilet_bath')->nullable();
            $table->string('water_supply')->nullable();
            $table->string('electricity')->nullable();
            $table->string('drainage')->nullable();

            // 7. FITTINGS & FIXTURES
            $table->text('fittings_summary')->nullable();

            // 8. REMARKS
            $table->text('remarks')->nullable();

            // 9. VALUATION DETAILS
            $table->string('valuation_basis')->nullable();
            $table->text('value_words')->nullable();
            $table->string('value_figures')->nullable();

            // 10 & 11
            $table->string('surveyor_name')->nullable();
            $table->string('surveyor_rank')->nullable();
            $table->string('chief_valuer')->nullable();

            // Metadata
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
        Schema::dropIfExists('valuation_reports');
    }
};
