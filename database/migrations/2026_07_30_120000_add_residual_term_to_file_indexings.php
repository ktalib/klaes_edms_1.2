<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        // Residual Term of the R of O, as displayed/printed (e.g. "54 Years").
        // Stored as free text rather than a year count because the Legal Search
        // editor lets an operator override the computed value with whatever the
        // file actually says.
        if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'residual_term')) {
            return;
        }

        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            $table->string('residual_term', 50)->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'residual_term')) {
            return;
        }

        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            $table->dropColumn('residual_term');
        });
    }
};
