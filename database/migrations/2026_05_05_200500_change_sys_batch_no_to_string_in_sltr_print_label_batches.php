<?php

use Illuminate\Database\Migrations\Migration;
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
        // Change sys_batch_no from INT to NVARCHAR(100) to support flexible batch numbering
        DB::connection('sqlsrv')->statement("
            ALTER TABLE sltr_print_label_batches ALTER COLUMN sys_batch_no NVARCHAR(100) NULL;
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert sys_batch_no back to INT
        // Note: This may fail if there is non-numeric data in the column
        DB::connection('sqlsrv')->statement("
            ALTER TABLE sltr_print_label_batches ALTER COLUMN sys_batch_no INT NULL;
        ");
    }
};
