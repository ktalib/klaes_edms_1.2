<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('oss_applications', function (Blueprint $table) {
            $table->string('passport_photo', 500)->nullable()->after('nature_of_agricultural');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('oss_applications', function (Blueprint $table) {
            $table->dropColumn('passport_photo');
        });
    }
};
