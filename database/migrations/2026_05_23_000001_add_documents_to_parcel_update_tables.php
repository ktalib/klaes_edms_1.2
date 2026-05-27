<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        $tables = [
            'plot_subdivision_applications',
            'plot_merger_applications',
            'plot_extension_applications',
        ];

        foreach ($tables as $table) {
            Schema::connection('sqlsrv')->table($table, function (Blueprint $t) {
                $t->string('ownership_document', 500)->nullable()->after('site_plan');
                $t->string('application_letter', 500)->nullable()->after('ownership_document');
                $t->string('means_of_id', 500)->nullable()->after('application_letter');
                $t->string('tax_clearance', 500)->nullable()->after('means_of_id');
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'plot_subdivision_applications',
            'plot_merger_applications',
            'plot_extension_applications',
        ];

        foreach ($tables as $table) {
            Schema::connection('sqlsrv')->table($table, function (Blueprint $t) {
                $t->dropColumn(['ownership_document', 'application_letter', 'means_of_id', 'tax_clearance']);
            });
        }
    }
};
