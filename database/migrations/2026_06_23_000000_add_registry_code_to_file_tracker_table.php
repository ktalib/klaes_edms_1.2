<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
            $table->string('registry_code', 20)->nullable()->after('origin_office_department');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
            $table->dropColumn('registry_code');
        });
    }
};
