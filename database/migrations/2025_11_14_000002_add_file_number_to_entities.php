<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds file_number field to entities table for enhanced tracking.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->table('entities', function (Blueprint $table) {
            // Add file_number field if it doesn't exist
            if (!Schema::connection('sqlsrv')->hasColumn('entities', 'file_number')) {
                $table->string('file_number', 50)->nullable()->after('entity_name')->comment('File or reference number');
                $table->index('file_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('entities', function (Blueprint $table) {
            // Drop file_number column if it exists
            if (Schema::connection('sqlsrv')->hasColumn('entities', 'file_number')) {
                $table->dropIndex(['file_number']);
                $table->dropColumn('file_number');
            }
        });
    }
};
