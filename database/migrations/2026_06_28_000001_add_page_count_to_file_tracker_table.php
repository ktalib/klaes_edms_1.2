<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
            $table->unsignedInteger('num_pages')->nullable()->after('registry_code');
            $table->boolean('in_digital_archive')->default(false)->after('num_pages');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
            $table->dropColumn(['num_pages', 'in_digital_archive']);
        });
    }
};
