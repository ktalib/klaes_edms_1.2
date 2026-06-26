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
            $table->integer('bill_from_year')->nullable();
            $table->integer('bill_to_year')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            $table->dropColumn(['bill_from_year', 'bill_to_year']);
        });
    }
};
