<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('file_indexings')) {
            Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
                if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'indexing_type')) {
                    $table->string('indexing_type', 50)->default('Regular')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('file_indexings')) {
            Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
                if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'indexing_type')) {
                    $table->dropColumn('indexing_type');
                }
            });
        }
    }
};
