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
            $table->dateTime('con_commissioned_at')->nullable()->after('commissioning_date');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('mls_file_no', function (Blueprint $table) {
            $table->dropColumn('con_commissioned_at');
        });
    }
};
