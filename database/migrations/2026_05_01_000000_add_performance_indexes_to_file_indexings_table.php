<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
        $connection = DB::connection('sqlsrv');

        // General performance indexes for file_indexings table
        $statements = [
            "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_file_indexings_file_number' AND object_id = OBJECT_ID('[dbo].[file_indexings]'))
             CREATE NONCLUSTERED INDEX [idx_file_indexings_file_number] ON [dbo].[file_indexings] ([file_number]);",
            
            "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_file_indexings_registry' AND object_id = OBJECT_ID('[dbo].[file_indexings]'))
             CREATE NONCLUSTERED INDEX [idx_file_indexings_registry] ON [dbo].[file_indexings] ([registry]);",
            
            "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_file_indexings_created_at' AND object_id = OBJECT_ID('[dbo].[file_indexings]'))
             CREATE NONCLUSTERED INDEX [idx_file_indexings_created_at] ON [dbo].[file_indexings] ([created_at]);",
            
            "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_file_indexings_created_by' AND object_id = OBJECT_ID('[dbo].[file_indexings]'))
             CREATE NONCLUSTERED INDEX [idx_file_indexings_created_by] ON [dbo].[file_indexings] ([created_by]);",

            "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_file_indexings_is_deleted' AND object_id = OBJECT_ID('[dbo].[file_indexings]'))
             CREATE NONCLUSTERED INDEX [idx_file_indexings_is_deleted] ON [dbo].[file_indexings] ([is_deleted]);",

            "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_file_indexings_tracking_id' AND object_id = OBJECT_ID('[dbo].[file_indexings]'))
             CREATE NONCLUSTERED INDEX [idx_file_indexings_tracking_id] ON [dbo].[file_indexings] ([tracking_id]);",
        ];

        foreach ($statements as $sql) {
            $connection->statement($sql);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $connection = DB::connection('sqlsrv');

        $drops = [
            "IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_file_indexings_file_number' AND object_id = OBJECT_ID('[dbo].[file_indexings]'))
             DROP INDEX [idx_file_indexings_file_number] ON [dbo].[file_indexings];",
            
            "IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_file_indexings_registry' AND object_id = OBJECT_ID('[dbo].[file_indexings]'))
             DROP INDEX [idx_file_indexings_registry] ON [dbo].[file_indexings];",
            
            "IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_file_indexings_created_at' AND object_id = OBJECT_ID('[dbo].[file_indexings]'))
             DROP INDEX [idx_file_indexings_created_at] ON [dbo].[file_indexings];",
            
            "IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_file_indexings_created_by' AND object_id = OBJECT_ID('[dbo].[file_indexings]'))
             DROP INDEX [idx_file_indexings_created_by] ON [dbo].[file_indexings];",

            "IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_file_indexings_is_deleted' AND object_id = OBJECT_ID('[dbo].[file_indexings]'))
             DROP INDEX [idx_file_indexings_is_deleted] ON [dbo].[file_indexings];",

            "IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_file_indexings_tracking_id' AND object_id = OBJECT_ID('[dbo].[file_indexings]'))
             DROP INDEX [idx_file_indexings_tracking_id] ON [dbo].[file_indexings];",
        ];

        foreach ($drops as $sql) {
            $connection->statement($sql);
        }
    }
};
