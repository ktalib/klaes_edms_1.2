<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';
    protected $table = 'file_indexings';

    /**
     * Physical Planning - Land (Shadow Files) matching.
     *
     * Stores, on each indexed file, the matched Physical Planning file number
     * plus the matching flag and date/time. Mirrors the pp_lands_* columns that
     * already exist on the fileNumber table.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
            if (! Schema::connection($this->connection)->hasColumn($this->table, 'pp_lands_fileno')) {
                $table->string('pp_lands_fileno', 255)->nullable();
            }
            if (! Schema::connection($this->connection)->hasColumn($this->table, 'pp_lands_matching')) {
                $table->integer('pp_lands_matching')->default(0);
            }
            if (! Schema::connection($this->connection)->hasColumn($this->table, 'pp_lands_date_matched')) {
                $table->date('pp_lands_date_matched')->nullable();
            }
            if (! Schema::connection($this->connection)->hasColumn($this->table, 'pp_lands_time_matched')) {
                $table->time('pp_lands_time_matched')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
            foreach (['pp_lands_fileno', 'pp_lands_matching', 'pp_lands_date_matched', 'pp_lands_time_matched'] as $column) {
                if (Schema::connection($this->connection)->hasColumn($this->table, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
