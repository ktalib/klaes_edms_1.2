<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    private array $tables = [
        'file_indexings',
        'pra',
        'fileNumber',
        'CofO_staging',
        'mls_file_no',
        'customers_staging',
        'entities_staging',
        'instrument_capture',
        'deed_registrations',
        'file_history_staging',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }

            Schema::connection('sqlsrv')->table($table, function (Blueprint $t) use ($table) {
                if (!Schema::connection('sqlsrv')->hasColumn($table, 'title_status')) {
                    $t->tinyInteger('title_status')->default(0)->nullable();
                }
                if (!Schema::connection('sqlsrv')->hasColumn($table, 'title_status_type')) {
                    $t->string('title_status_type', 100)->nullable();
                }
                if (!Schema::connection('sqlsrv')->hasColumn($table, 'title_status_remark')) {
                    $t->text('title_status_remark')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }

            Schema::connection('sqlsrv')->table($table, function (Blueprint $t) use ($table) {
                $cols = ['title_status', 'title_status_type', 'title_status_remark'];
                $existing = array_filter($cols, fn($c) => Schema::connection('sqlsrv')->hasColumn($table, $c));
                if ($existing) {
                    $t->dropColumn(array_values($existing));
                }
            });
        }
    }
};
