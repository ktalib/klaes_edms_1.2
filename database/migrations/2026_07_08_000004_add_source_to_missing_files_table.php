<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection($this->connection)->table('missing_files', function (Blueprint $table) {
            // Where the missing report came from: QUICK_SEARCH | DFR | null (manual capture).
            $table->string('source', 50)->nullable()->after('status');
            // The File Search Request that produced this report (auto-created when the
            // SCB Monitor marks it Not Found — Missing), so a revert can undo it.
            $table->string('request_no', 50)->nullable()->after('source');

            $table->index('request_no');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('missing_files', function (Blueprint $table) {
            $table->dropIndex(['request_no']);
            $table->dropColumn(['source', 'request_no']);
        });
    }
};
