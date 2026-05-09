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
        Schema::table('valuation_compensations', function (Blueprint $table) {
            if (!Schema::hasColumn('valuation_compensations', 'project_fileno')) {
                $table->string('project_fileno', 100)->nullable()->after('project_id');
            }
            // our_ref already exists, but we ensure it's nullable if we want manual entry
            $table->string('our_ref', 100)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('valuation_compensations', function (Blueprint $table) {
            $table->dropColumn('project_fileno');
        });
    }
};
