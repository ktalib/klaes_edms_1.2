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
        Schema::connection('sqlsrv')->table('file_indexing_links', function (Blueprint $table) {
            $table->boolean('mfile')->default(0)->after('file_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->table('file_indexing_links', function (Blueprint $table) {
            $table->dropColumn('mfile');
        });
    }
};
