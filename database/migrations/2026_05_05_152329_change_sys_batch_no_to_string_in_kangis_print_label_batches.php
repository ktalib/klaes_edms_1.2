<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::connection('sqlsrv')->statement('ALTER TABLE kangis_print_label_batches ALTER COLUMN sys_batch_no NVARCHAR(100) NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::connection('sqlsrv')->statement('ALTER TABLE kangis_print_label_batches ALTER COLUMN sys_batch_no INT NULL');
    }
};
