<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            // Parcel Update classification flag — 'Dummy File' or 'Temporary'
            // (mutually exclusive with the "Normal"/actionable title-status options).
            $table->string('file_classification', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            $table->dropColumn(['file_classification']);
        });
    }
};
