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
        Schema::connection('sqlsrv')->table('blind_scannings', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('blind_scannings', 'registry_type')) {
                $table->string('registry_type')->default('lands')->index();
            }
        });

        // Backfill existing records
        DB::connection('sqlsrv')->table('blind_scannings')->whereNull('registry_type')->update(['registry_type' => 'lands']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('blind_scannings', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('blind_scannings', 'registry_type')) {
                $table->dropColumn('registry_type');
            }
        });
    }
};

