<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Adds latitude/longitude columns so the file indexing form can persist
     * the geocoded coordinates of the property location (pinned via Google Maps
     * on the create/edit screen).
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->after('location');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'longitude')) {
                $table->dropColumn('longitude');
            }
            if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'latitude')) {
                $table->dropColumn('latitude');
            }
        });
    }
};
