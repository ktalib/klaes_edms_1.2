<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection to use for this migration.
     */
    protected $connection = 'sqlsrv';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('duplicate_fileno', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('comment');
            $table->string('source', 50)->default('csv')->after('created_by'); // 'csv' or 'manual'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('duplicate_fileno', function (Blueprint $table) {
            $table->dropColumn(['created_by', 'source']);
        });
    }
};
