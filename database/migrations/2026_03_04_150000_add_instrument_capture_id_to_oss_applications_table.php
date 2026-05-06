<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('oss_applications', function (Blueprint $table) {
            $table->unsignedBigInteger('instrument_capture_id')->nullable()->after('id');
            $table->index('instrument_capture_id', 'oss_applications_instrument_capture_id_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('oss_applications', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('oss_applications', 'instrument_capture_id')) {
                $table->dropIndex('oss_applications_instrument_capture_id_idx');
                $table->dropColumn('instrument_capture_id');
            }
        });
    }
};
    
  