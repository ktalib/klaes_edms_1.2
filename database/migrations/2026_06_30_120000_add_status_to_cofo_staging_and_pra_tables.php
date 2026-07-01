<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /**
     * Title/instrument lifecycle status captured during File Indexing for CofO & RofO.
     * Values: Active (default), Normal Cancellation, Total Cancellation,
     * Normal Termination of Application.
     */
    private array $tables = [
        'CofO_staging',
        'pra',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }

            if (Schema::connection('sqlsrv')->hasColumn($table, 'status')) {
                continue;
            }

            Schema::connection('sqlsrv')->table($table, function (Blueprint $t) {
                $t->string('status', 50)->default('Active')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }

            if (!Schema::connection('sqlsrv')->hasColumn($table, 'status')) {
                continue;
            }

            Schema::connection('sqlsrv')->table($table, function (Blueprint $t) {
                $t->dropColumn('status');
            });
        }
    }
};
