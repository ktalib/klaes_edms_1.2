<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Use raw SQL to rename column in SQL Server
        DB::connection('sqlsrv')->statement("
            EXEC sp_rename 'grouping.pseudo_fileno', 'awaiting_fileno', 'COLUMN'
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Use raw SQL to rename column back
        DB::connection('sqlsrv')->statement("
            EXEC sp_rename 'grouping.awaiting_fileno', 'pseudo_fileno', 'COLUMN'
        ");
    }
};
