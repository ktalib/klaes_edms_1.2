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
            // Additional/related file number captured for Re-grant ("See ..." on the physical file).
            // Name kept generic ("see_fileno") so the label can be renamed later without a schema change.
            if (!Schema::connection('sqlsrv')->hasColumn('title_status_applications', 'see_fileno')) {
                $table->string('see_fileno', 255)->nullable()->after('file_no');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('title_status_applications', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('title_status_applications', 'see_fileno')) {
                $table->dropColumn('see_fileno');
            }
        });
    }
};
