<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The connection name for the migration.
     */
    protected $connection = 'sqlsrv';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->table('file_tracker', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('file_tracker', 'date_requested')) {
                $table->datetime('date_requested')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('file_tracker', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn('file_tracker', 'date_requested')) {
                $table->dropColumn('date_requested');
            }
        });
    }
};
