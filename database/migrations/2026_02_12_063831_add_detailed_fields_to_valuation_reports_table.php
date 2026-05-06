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
        Schema::table('valuation_reports', function (Blueprint $table) {
            $table->string('neighborhood_characteristics')->nullable()->after('lga');
            $table->string('units_per_block')->nullable()->after('num_blocks');
            $table->string('walls_stage')->nullable()->after('walls_type');

            $table->string('boundary_wall_type')->nullable()->after('ancillary_bldgs');
            $table->string('boundary_wall_height')->nullable()->after('boundary_wall_type');
            $table->string('boundary_wall_finish')->nullable()->after('boundary_wall_height');
            $table->string('gate_type')->nullable()->after('boundary_wall_finish');

            $table->string('int_doors')->nullable()->after('ext_doors');
            $table->string('door_security_proof')->nullable()->after('int_doors');

            $table->string('fittings_sitting')->nullable()->after('fittings_summary');
            $table->string('fittings_dining')->nullable()->after('fittings_sitting');
            $table->string('fittings_bedroom')->nullable()->after('fittings_dining');
            $table->string('fittings_toilet')->nullable()->after('fittings_bedroom');
            $table->string('fittings_bath')->nullable()->after('fittings_toilet');
            $table->string('fittings_kitchen')->nullable()->after('fittings_bath');
            $table->string('fittings_store')->nullable()->after('fittings_kitchen');
            $table->string('fittings_bq')->nullable()->after('fittings_store');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('valuation_reports', function (Blueprint $table) {
            $table->dropColumn([
                'neighborhood_characteristics',
                'units_per_block',
                'walls_stage',
                'boundary_wall_type',
                'boundary_wall_height',
                'boundary_wall_finish',
                'gate_type',
                'int_doors',
                'door_security_proof',
                'fittings_sitting',
                'fittings_dining',
                'fittings_bedroom',
                'fittings_toilet',
                'fittings_bath',
                'fittings_kitchen',
                'fittings_store',
                'fittings_bq',
            ]);
        });
    }
};
