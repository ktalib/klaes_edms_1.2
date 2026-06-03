<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('grouping', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('grouping', 'cadastral_file_generated')) {
                $table->boolean('cadastral_file_generated')->default(false)
                      ->comment('Flag: whether a cadastral label/file has been generated for this grouping row');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('grouping', 'cadastral_context')) {
                $table->text('cadastral_context')->nullable()
                      ->comment('Free-text context recorded when the cadastral file was generated');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('grouping', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('grouping', 'cadastral_file_generated')) {
                $table->dropColumn('cadastral_file_generated');
            }

            if (Schema::connection('sqlsrv')->hasColumn('grouping', 'cadastral_context')) {
                $table->dropColumn('cadastral_context');
            }
        });
    }
};
