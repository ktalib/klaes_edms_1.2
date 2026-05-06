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
        if (!Schema::connection('sqlsrv')->hasTable('buyer_list')) {
            return;
        }

        if (!Schema::connection('sqlsrv')->hasColumn('buyer_list', 'section_number')) {
            Schema::connection('sqlsrv')->table('buyer_list', function (Blueprint $table) {
                $table->string('section_number', 100)->nullable()->after('unit_no');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('buyer_list')) {
            return;
        }

        if (Schema::connection('sqlsrv')->hasColumn('buyer_list', 'section_number')) {
            Schema::connection('sqlsrv')->table('buyer_list', function (Blueprint $table) {
                $table->dropColumn('section_number');
            });
        }
    }
};
