<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';
    
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'country_code')) {
                $table->string('country_code', 10)->nullable()->default('+234')->after('lga');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'phone')) {
                $table->string('phone', 20)->nullable()->after('country_code');
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
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            $table->dropColumn(['country_code', 'phone']);
        });
    }
};
