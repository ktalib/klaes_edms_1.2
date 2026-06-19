<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection($this->connection)->table('spa_certificates', function (Blueprint $table) {
            // The file number assigned after the change of purpose. `file_number`
            // remains the old (original) file number.
            $table->string('new_file_number', 255)->nullable()->after('file_number');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('spa_certificates', function (Blueprint $table) {
            $table->dropColumn('new_file_number');
        });
    }
};
