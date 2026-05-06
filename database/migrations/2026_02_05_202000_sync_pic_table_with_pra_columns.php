<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations - Sync PIC table with PRA table columns
     */
    public function up(): void
    {
        $connection = DB::connection('sqlsrv');

        // Array of columns to add to PIC table (matching PRA structure)
        $columnsToAdd = [
            // Date and time fields
            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'deeds_date')
                ALTER TABLE [dbo].[pic] ADD [deeds_date] DATE NULL",

            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'deeds_time')
                ALTER TABLE [dbo].[pic] ADD [deeds_time] VARCHAR(10) NULL",

            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'reg_date')
                ALTER TABLE [dbo].[pic] ADD [reg_date] DATE NULL",

            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'reg_time')
                ALTER TABLE [dbo].[pic] ADD [reg_time] VARCHAR(10) NULL",

            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'regDate')
                ALTER TABLE [dbo].[pic] ADD [regDate] DATE NULL",

            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'regTime')
                ALTER TABLE [dbo].[pic] ADD [regTime] VARCHAR(10) NULL",

            // Generic party fields
            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'party_1')
                ALTER TABLE [dbo].[pic] ADD [party_1] NVARCHAR(500) NULL",

            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'party_2')
                ALTER TABLE [dbo].[pic] ADD [party_2] NVARCHAR(500) NULL",

            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'party_3')
                ALTER TABLE [dbo].[pic] ADD [party_3] NVARCHAR(500) NULL",

            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'party_4')
                ALTER TABLE [dbo].[pic] ADD [party_4] NVARCHAR(500) NULL",

            // Additional party fields for tripartite transactions
            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'Mortgagor_2')
                ALTER TABLE [dbo].[pic] ADD [Mortgagor_2] NVARCHAR(500) NULL",

            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'Surrenderor_2')
                ALTER TABLE [dbo].[pic] ADD [Surrenderor_2] NVARCHAR(500) NULL",

            // Transaction-specific party fields
            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'Donor')
                ALTER TABLE [dbo].[pic] ADD [Donor] NVARCHAR(500) NULL",

            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'Donee')
                ALTER TABLE [dbo].[pic] ADD [Donee] NVARCHAR(500) NULL",

            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'Vendor')
                ALTER TABLE [dbo].[pic] ADD [Vendor] NVARCHAR(500) NULL",

            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'Purchaser')
                ALTER TABLE [dbo].[pic] ADD [Purchaser] NVARCHAR(500) NULL",

            // File number fields
            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'fileno')
                ALTER TABLE [dbo].[pic] ADD [fileno] NVARCHAR(255) NULL",

            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'np_fileno')
                ALTER TABLE [dbo].[pic] ADD [np_fileno] NVARCHAR(255) NULL",

            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'temp_fileno')
                ALTER TABLE [dbo].[pic] ADD [temp_fileno] NVARCHAR(255) NULL",

            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'related_file_number')
                ALTER TABLE [dbo].[pic] ADD [related_file_number] NVARCHAR(255) NULL",

            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'related_fileno')
                ALTER TABLE [dbo].[pic] ADD [related_fileno] NVARCHAR(255) NULL",

            // Metadata fields
            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'is_mapped')
                ALTER TABLE [dbo].[pic] ADD [is_mapped] BIT NULL DEFAULT 0",

            "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'updated_by')
                ALTER TABLE [dbo].[pic] ADD [updated_by] INT NULL",
        ];

        // Execute each ALTER TABLE statement
        foreach ($columnsToAdd as $sql) {
            try {
                $connection->statement($sql);
            } catch (\Exception $e) {
                // Log the error but continue with other columns
                \Log::warning("Failed to add column to PIC table: " . $e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        // We don't remove columns in down() to preserve data
        // If you need to remove columns, uncomment and modify:
        /*
        Schema::connection('sqlsrv')->table('pic', function ($table) {
            // Drop columns added in up()
            $table->dropColumn([
                'deeds_date', 'deeds_time', 'reg_date', 'reg_time',
                'regDate', 'regTime', 'party_1', 'party_2', 'party_3', 'party_4',
                'Mortgagor_2', 'Surrenderor_2', 'Donor', 'Donee', 'Vendor', 'Purchaser',
                'fileno', 'np_fileno', 'temp_fileno', 'related_file_number', 'related_fileno',
                'is_mapped', 'updated_by'
            ]);
        });
        */
    }
};
