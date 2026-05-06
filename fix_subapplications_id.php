<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    echo "Starting subapplications table fix...\n";
    $connection = DB::connection('sqlsrv');

    // 1. Create a backup/temporary table with the correct identity column
    echo "Creating temporary table subapplications_new...\n";
    $connection->statement("IF OBJECT_ID('[dbo].[subapplications_new]', 'U') IS NOT NULL DROP TABLE [dbo].[subapplications_new]");
    $connection->statement("
        CREATE TABLE [dbo].[subapplications_new] (
            [id] [int] IDENTITY(1,1) NOT NULL,
            [main_application_id] [int] NULL,
            [applicant_type] [nvarchar](255) NULL,
            [fileno] [nvarchar](255) NULL,
            [np_fileno] [nvarchar](255) NULL,
            [applicant_title] [nvarchar](255) NULL,
            [first_name] [nvarchar](255) NULL,
            [middle_name] [nvarchar](255) NULL,
            [surname] [nvarchar](255) NULL,
            [passport] [nvarchar](255) NULL,
            [corporate_name] [nvarchar](255) NULL,
            [rc_number] [nvarchar](255) NULL,
            [multiple_owners_names] [nvarchar](max) NULL,
            [multiple_owners_address] [nvarchar](max) NULL,
            [multiple_owners_email] [nvarchar](max) NULL,
            [multiple_owners_phone] [nvarchar](max) NULL,
            [multiple_owners_passport] [nvarchar](max) NULL,
            [multiple_owners_identification_type] [nvarchar](max) NULL,
            [multiple_owners_identification_image] [nvarchar](max) NULL,
            [address] [nvarchar](max) NULL,
            [phone_number] [nvarchar](255) NULL,
            [email] [nvarchar](255) NULL,
            [identification_type] [nvarchar](255) NULL,
            [id_document] [nvarchar](255) NULL,
            [block_number] [nvarchar](255) NULL,
            [floor_number] [nvarchar](255) NULL,
            [unit_number] [nvarchar](255) NULL,
            [unit_size] [nvarchar](255) NULL,
            [main_id] [int] NULL,
            [application_status] [nvarchar](255) NULL,
            [planning_recommendation_status] [nvarchar](255) NULL,
            [application_fee] [decimal](18, 2) NULL,
            [processing_fee] [decimal](18, 2) NULL,
            [site_plan_fee] [decimal](18, 2) NULL,
            [application_date] [datetime] NULL,
            [payment_date] [datetime] NULL,
            [receipt_number] [nvarchar](255) NULL,
            [created_at] [datetime] NULL,
            [updated_at] [datetime] NULL,
            CONSTRAINT [PK_subapplications_new] PRIMARY KEY CLUSTERED ([id] ASC)
        )
    ");

    // 2. Migrate data from old table to new table
    echo "Migrating data from subapplications to subapplications_new...\n";
    
    // We must run IDENTITY_INSERT and the INSERT in the same batch or transaction
    $connection->unprepared("
        SET IDENTITY_INSERT [dbo].[subapplications_new] ON;
        
        INSERT INTO [dbo].[subapplications_new] (
            [id], [main_application_id], [applicant_type], [fileno], [np_fileno], [applicant_title], [first_name], [middle_name], [surname], [passport], [corporate_name], [rc_number], [multiple_owners_names], [multiple_owners_address], [multiple_owners_email], [multiple_owners_phone], [multiple_owners_passport], [multiple_owners_identification_type], [multiple_owners_identification_image], [address], [phone_number], [email], [identification_type], [id_document], [block_number], [floor_number], [unit_number], [unit_size], [main_id], [application_status], [planning_recommendation_status], [application_fee], [processing_fee], [site_plan_fee], [application_date], [payment_date], [receipt_number], [created_at], [updated_at]
        )
        SELECT 
            CAST([id] AS int), [main_application_id], [applicant_type], [fileno], [np_fileno], [applicant_title], [first_name], [middle_name], [surname], [passport], [corporate_name], [rc_number], [multiple_owners_names], [multiple_owners_address], [multiple_owners_email], [multiple_owners_phone], [multiple_owners_passport], [multiple_owners_identification_type], [multiple_owners_identification_image], [address], [phone_number], [email], [identification_type], [id_document], [block_number], [floor_number], [unit_number], [unit_size], [main_id], [application_status], [planning_recommendation_status], [application_fee], [processing_fee], [site_plan_fee], [application_date], [payment_date], [receipt_number], [created_at], [updated_at]
        FROM [dbo].[subapplications];
        
        SET IDENTITY_INSERT [dbo].[subapplications_new] OFF;
    ");

    // 3. Rename tables
    echo "Renaming tables...\n";
    $connection->statement("EXEC sp_rename 'subapplications', 'subapplications_old'");
    $connection->statement("EXEC sp_rename 'subapplications_new', 'subapplications'");

    echo "Fix completed successfully!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    // Cleanup if new table was created but not renamed
    try {
        if (Schema::hasTable('subapplications_new')) {
            DB::connection('sqlsrv')->statement("DROP TABLE [dbo].[subapplications_new]");
        }
    } catch (\Exception $e2) {}
}
