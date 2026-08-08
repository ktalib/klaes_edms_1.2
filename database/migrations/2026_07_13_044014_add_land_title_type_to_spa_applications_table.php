<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection($this->connection)->table('spa_applications', function (Blueprint $table) {
            // statutory | customary — customary records get a system-generated
            // temporary "SPAS-YYYY-####" file number instead of a picked file.
            $table->string('land_title_type', 20)->default('statutory')->after('is_indexed');
        });
    }


-- UP Migration
ALTER TABLE spa_applications
ADD land_title_type NVARCHAR(MAX) NULL;


    public function down(): void
    {
        Schema::connection($this->connection)->table('spa_applications', function (Blueprint $table) {
            $table->dropColumn('land_title_type');
        });
    }
};
