<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
            // Pages counted when the file is logged back to the registry.
            // num_pages stays as the original count recorded at log-out.
            $table->unsignedInteger('returned_num_pages')->nullable()->after('num_pages');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
            $table->dropColumn('returned_num_pages');
        });
    }
};
