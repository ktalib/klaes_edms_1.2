<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('manual_file_linkages')) {
            return;
        }

        Schema::connection('sqlsrv')->table('manual_file_linkages', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('manual_file_linkages', 'holding_file_no')) {
                // Auto-generated temporary holding file number (HOLD-...). Connects all
                // stages (merger → subdivision → change of purpose) of one transaction chain.
                $table->string('holding_file_no', 100)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('manual_file_linkages')) {
            return;
        }

        Schema::connection('sqlsrv')->table('manual_file_linkages', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('manual_file_linkages', 'holding_file_no')) {
                $table->dropColumn('holding_file_no');
            }
        });
    }
};
