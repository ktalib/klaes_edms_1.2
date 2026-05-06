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
        Schema::connection('sqlsrv')->table('file_indexing_links', function (Blueprint $table) {
            $table->unsignedBigInteger('file_indexing_id')->nullable()->after('id');
            $table->index('file_indexing_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('file_indexing_links', function (Blueprint $table) {
            $table->dropIndex(['file_indexing_id']);
            $table->dropColumn('file_indexing_id');
        });
    }
};
