<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('sqlsrv')->statement("
            UPDATE file_indexings
            SET general_registry = CASE
                WHEN UPPER(file_number) LIKE 'ST-%' THEN 'ST Registry'
                WHEN UPPER(file_number) LIKE 'DCIV-%' OR UPPER(file_number) LIKE 'DCIV/%' THEN 'DCIV Registry'
                WHEN UPPER(file_number) LIKE 'SLTR-%' OR UPPER(file_number) LIKE 'SLTR/%' THEN 'SLTR Registry'
                WHEN UPPER(file_number) LIKE 'SIT-%' OR UPPER(file_number) LIKE 'SIT/%' THEN 'SIT Registry'
                WHEN UPPER(file_number) LIKE 'GKN-%' OR UPPER(file_number) LIKE 'GKN/%' OR UPPER(file_number) LIKE 'GKN %'
                  OR UPPER(file_number) LIKE 'LPKN-%' OR UPPER(file_number) LIKE 'LPKN/%' OR UPPER(file_number) LIKE 'LPKN %'
                    THEN 'Survey Registry'
                WHEN UPPER(file_number) LIKE 'KNML%' OR UPPER(file_number) LIKE 'MLKN%'
                  OR UPPER(file_number) LIKE 'MNKL%' OR UPPER(file_number) LIKE 'KNGP%'
                    THEN 'KANGIS Registry'
                WHEN UPPER(file_number) LIKE 'RES-%' OR UPPER(file_number) LIKE 'RES/%'
                  OR UPPER(file_number) LIKE 'COM-%' OR UPPER(file_number) LIKE 'COM/%'
                  OR UPPER(file_number) LIKE 'IND-%' OR UPPER(file_number) LIKE 'IND/%'
                  OR UPPER(file_number) LIKE 'AG-%' OR UPPER(file_number) LIKE 'AG/%'
                  OR UPPER(file_number) LIKE 'MISC-%' OR UPPER(file_number) LIKE 'MISC/%'
                  OR UPPER(file_number) LIKE 'CON-RES%'
                  OR UPPER(file_number) LIKE 'CON-COM%'
                  OR UPPER(file_number) LIKE 'CON-IND%'
                  OR UPPER(file_number) LIKE 'CON-AG%'
                  OR UPPER(file_number) LIKE 'CON-MISC%'
                    THEN 'Lands Registry'
                ELSE NULL
            END
            WHERE general_registry IS NULL
              AND file_number IS NOT NULL
        ");
    }

    public function down(): void
    {
        // No safe rollback — cannot distinguish previously-null from manually-set values
    }
};
