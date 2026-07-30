<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('mls_file_no', function (Blueprint $table) {
            // Applicant gender captured during MLS/OSS file commissioning. Male | Female.
            $table->string('gender', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('mls_file_no', function (Blueprint $table) {
            $table->dropColumn(['gender']);
        });
    }
};
