<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Drop all possible unique constraints/indexes from sltr_print_label_batches that involve sys_batch_no
        // uq_sltr_batch_prefix_sysbatchno is the one from the migration file
        // uq_sltr_plb_prefix_sysbatch is the one mentioned in the error message
        // uq_kangis_batch_prefix_sysbatchno is mentioned in user's report (might be misnamed in DB)
        
        $constraints = [
            'uq_sltr_batch_prefix_sysbatchno',
            'uq_sltr_plb_prefix_sysbatch',
            'uq_kangis_batch_prefix_sysbatchno'
        ];

        foreach ($constraints as $constraint) {
            DB::connection('sqlsrv')->statement("
                IF EXISTS (SELECT * FROM sys.objects WHERE name = '$constraint' AND type = 'UQ')
                BEGIN
                    ALTER TABLE sltr_print_label_batches DROP CONSTRAINT $constraint;
                END
                ELSE IF EXISTS (SELECT * FROM sys.indexes WHERE name = '$constraint' AND object_id = OBJECT_ID('sltr_print_label_batches'))
                BEGIN
                    DROP INDEX $constraint ON sltr_print_label_batches;
                END
            ");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Restore the unique constraint
        DB::connection('sqlsrv')->statement("
            IF NOT EXISTS (SELECT * FROM sys.objects WHERE name = 'uq_sltr_batch_prefix_sysbatchno' AND type = 'UQ')
            BEGIN
                ALTER TABLE sltr_print_label_batches ADD CONSTRAINT uq_sltr_batch_prefix_sysbatchno UNIQUE (prefix, sys_batch_no);
            END
        ");
    }
};
