<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('file_tracker', 'destination')) {
                $table->string('destination')->nullable()->after('department');
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
        Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
            $table->dropColumn('destination');
        });
    }
};
