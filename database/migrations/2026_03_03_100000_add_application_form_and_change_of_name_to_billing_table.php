<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('billing', function (Blueprint $table) {
            $table->string('Application_Form')->nullable()->after('Processing_Fee');
            $table->string('Change_of_Name')->nullable()->after('Application_Form');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('billing', function (Blueprint $table) {
            $table->dropColumn(['Application_Form', 'Change_of_Name']);
        });
    }
};
