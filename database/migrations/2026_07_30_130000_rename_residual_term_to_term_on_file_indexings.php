<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /**
     * The Legal Search editor persists the TERM of the R of O (e.g. "99 Years"),
     * not the Residual Term — the latter stays derived from the term and the
     * commencement date. Replaces the residual_term column added earlier the
     * same day (which carried no meaningful data).
     */
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasColumn('file_indexings', 'term')) {
            $schema->table('file_indexings', function (Blueprint $table) {
                $table->string('term', 50)->nullable();
            });
        }

        if ($schema->hasColumn('file_indexings', 'residual_term')) {
            $schema->table('file_indexings', function (Blueprint $table) {
                $table->dropColumn('residual_term');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasColumn('file_indexings', 'residual_term')) {
            $schema->table('file_indexings', function (Blueprint $table) {
                $table->string('residual_term', 50)->nullable();
            });
        }

        if ($schema->hasColumn('file_indexings', 'term')) {
            $schema->table('file_indexings', function (Blueprint $table) {
                $table->dropColumn('term');
            });
        }
    }
};
