<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Adds users.fr_permissions — the File Request role flag. A value of 'SCB'
     * designates the user as an SCB Monitor (mobile file searcher) who receives
     * File Requests (in-app + email) from the Quick Search module.
     */
    public function up()
    {
        Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('users', 'fr_permissions')) {
                $table->string('fr_permissions', 20)->nullable()->after('dfr_permissions');
            }
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('users', 'fr_permissions')) {
                $table->dropColumn('fr_permissions');
            }
        });
    }
};
