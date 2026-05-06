<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBuyerListIdToStFileNumbers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
            // Add buyer_list_id column to link PuA file numbers with buyer records
            if (!Schema::connection('sqlsrv')->hasColumn('st_file_numbers', 'buyer_list_id')) {
                $table->unsignedBigInteger('buyer_list_id')->nullable()->after('parent_id');
            }

            // Add foreign key constraint (optional - only if buyer_list table has proper primary key)
            // $table->foreign('buyer_list_id')->references('id')->on('buyer_list')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *@
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
            // Drop foreign key if it exists
            // $table->dropForeign(['buyer_list_id']);

            $table->dropColumn('buyer_list_id');
        });
    }
}
