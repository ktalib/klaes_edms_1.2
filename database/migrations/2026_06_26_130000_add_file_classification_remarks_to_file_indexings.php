<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            // Per-flag comments for the Parcel Update classification flags, stored as a
            // JSON object keyed by type, e.g. {"Dummy File":"...","Temporary":"..."}.
            $table->text('file_classification_remarks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            $table->dropColumn(['file_classification_remarks']);
        });
    }
};
