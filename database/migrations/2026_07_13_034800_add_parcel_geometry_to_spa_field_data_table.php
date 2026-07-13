<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection($this->connection)->table('spa_field_data', function (Blueprint $table) {
            $table->text('parcel_geometry')->nullable()->after('coordinates'); // JSON: GeoJSON polygon of the traced plot boundary
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('spa_field_data', function (Blueprint $table) {
            $table->dropColumn('parcel_geometry');
        });
    }
};
