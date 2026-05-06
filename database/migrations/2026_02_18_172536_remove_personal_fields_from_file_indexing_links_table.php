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
        Schema::table('file_indexing_links', function (Blueprint $table) {
            $columns = ['dob', 'nin', 'tin', 'phone', 'rc_no', 'residence_address', 'country_code'];
            $columnsToDrop = [];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('file_indexing_links', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
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
        Schema::table('file_indexing_links', function (Blueprint $table) {
            $table->date('dob')->nullable();
            $table->string('nin')->nullable();
            $table->string('tin')->nullable();
            $table->string('phone')->nullable();
            $table->string('rc_no')->nullable();
            $table->text('residence_address')->nullable();
            $table->string('country_code')->nullable();
        });
    }
};
