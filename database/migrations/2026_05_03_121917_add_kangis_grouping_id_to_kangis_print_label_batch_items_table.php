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
        Schema::table('kangis_print_label_batch_items', function (Blueprint $table) {
            $table->bigInteger('kangis_grouping_id')->nullable()->after('file_indexing_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('kangis_print_label_batch_items', function (Blueprint $table) {
            $table->dropColumn('kangis_grouping_id');
        });
    }
};
