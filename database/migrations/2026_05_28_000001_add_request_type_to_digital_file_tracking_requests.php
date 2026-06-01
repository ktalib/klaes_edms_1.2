<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection($this->connection)
            ->table('digital_file_tracking_requests', function (Blueprint $table) {
                // 'Physical' = standard file dispatch; 'Digital' = temporary digital copy
                $table->string('request_type', 20)->default('Physical')->after('request_no');
            });
    }

    public function down(): void
    {
        Schema::connection($this->connection)
            ->table('digital_file_tracking_requests', function (Blueprint $table) {
                $table->dropColumn('request_type');
            });
    }
};
