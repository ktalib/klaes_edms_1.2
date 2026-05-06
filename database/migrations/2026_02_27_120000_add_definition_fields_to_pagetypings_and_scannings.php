<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (Schema::connection('sqlsrv')->hasTable('pagetypings')) {
            Schema::connection('sqlsrv')->table('pagetypings', function (Blueprint $table) {
                if (!Schema::connection('sqlsrv')->hasColumn('pagetypings', 'definition')) {
                    $table->integer('definition')->nullable()->default(0)->after('serial_number');
                }
                if (!Schema::connection('sqlsrv')->hasColumn('pagetypings', 'definition_code')) {
                    $table->string('definition_code', 255)->nullable()->after('page_code');
                }
            });

            DB::connection('sqlsrv')->statement("UPDATE pagetypings SET definition = 0 WHERE definition IS NULL");
            DB::connection('sqlsrv')->statement("UPDATE pagetypings SET definition_code = CONCAT(CAST(ISNULL(definition, 0) AS VARCHAR(10)), '-', page_code) WHERE (definition_code IS NULL OR LTRIM(RTRIM(definition_code)) = '') AND page_code IS NOT NULL AND LTRIM(RTRIM(page_code)) <> ''");
        }

        if (Schema::connection('sqlsrv')->hasTable('scannings')) {
            Schema::connection('sqlsrv')->table('scannings', function (Blueprint $table) {
                if (!Schema::connection('sqlsrv')->hasColumn('scannings', 'definition')) {
                    $table->integer('definition')->nullable()->default(0)->after('display_order');
                }
                if (!Schema::connection('sqlsrv')->hasColumn('scannings', 'definition_code')) {
                    $table->string('definition_code', 255)->nullable()->after('document_type');
                }
            });

            DB::connection('sqlsrv')->statement("UPDATE scannings SET definition = 0 WHERE definition IS NULL");
            DB::connection('sqlsrv')->statement("UPDATE s SET s.definition_code = CONCAT(CAST(ISNULL(s.definition, 0) AS VARCHAR(10)), '-', fi.file_number) FROM scannings s INNER JOIN file_indexings fi ON fi.id = s.file_indexing_id WHERE (s.definition_code IS NULL OR LTRIM(RTRIM(s.definition_code)) = '') AND fi.file_number IS NOT NULL AND LTRIM(RTRIM(fi.file_number)) <> ''");
        }
    }

    public function down()
    {
        if (Schema::connection('sqlsrv')->hasTable('pagetypings')) {
            Schema::connection('sqlsrv')->table('pagetypings', function (Blueprint $table) {
                if (Schema::connection('sqlsrv')->hasColumn('pagetypings', 'definition_code')) {
                    $table->dropColumn('definition_code');
                }
                if (Schema::connection('sqlsrv')->hasColumn('pagetypings', 'definition')) {
                    $table->dropColumn('definition');
                }
            });
        }

        if (Schema::connection('sqlsrv')->hasTable('scannings')) {
            Schema::connection('sqlsrv')->table('scannings', function (Blueprint $table) {
                if (Schema::connection('sqlsrv')->hasColumn('scannings', 'definition_code')) {
                    $table->dropColumn('definition_code');
                }
                if (Schema::connection('sqlsrv')->hasColumn('scannings', 'definition')) {
                    $table->dropColumn('definition');
                }
            });
        }
    }
};
