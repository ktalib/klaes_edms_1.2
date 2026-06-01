<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('title_status_applications', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('title_status_applications', 'initiated_by')) {
                $table->string('initiated_by', 50)->nullable()->after('authority_reference');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('title_status_applications', 'reason')) {
                $table->text('reason')->nullable()->after('initiated_by');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('title_status_applications', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('title_status_applications', 'reason')) {
                $table->dropColumn('reason');
            }
            if (Schema::connection('sqlsrv')->hasColumn('title_status_applications', 'initiated_by')) {
                $table->dropColumn('initiated_by');
            }
        });
    }
};
