<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection($this->connection)->table('missing_files', function (Blueprint $table) {
            $table->string('property_location')->nullable()->after('file_title');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('missing_files', function (Blueprint $table) {
            $table->dropColumn('property_location');
        });
    }
};
