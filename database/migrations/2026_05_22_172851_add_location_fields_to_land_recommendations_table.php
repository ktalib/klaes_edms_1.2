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
        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            $table->string('house_no')->nullable()->after('plot_number');
            $table->string('street_name')->nullable()->after('house_no');
            $table->string('district')->nullable()->after('street_name');
            $table->string('state')->nullable()->after('district');
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            $table->dropColumn(['house_no', 'street_name', 'district', 'state']);
        });
    }
};
