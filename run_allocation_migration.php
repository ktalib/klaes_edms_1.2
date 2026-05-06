<?php
// Run this from the project root: php run_allocation_migration.php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$sql = <<<'EOSQL'
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = 'allocation_list_stage'
)
BEGIN
    CREATE TABLE [dbo].[allocation_list_stage] (
        [id]           INT IDENTITY(1,1) NOT NULL,
        [title]        NVARCHAR(50)      NULL,
        [first_name]   NVARCHAR(100)     NOT NULL,
        [middle_name]  NVARCHAR(100)     NULL,
        [last_name]    NVARCHAR(100)     NOT NULL,
        [plot_number]  NVARCHAR(50)      NULL,
        [district]     NVARCHAR(100)     NULL,
        [lga]          NVARCHAR(100)     NULL,
        [state]        NVARCHAR(100)     NULL     DEFAULT N'Kano',
        [allocated_by] NVARCHAR(50)      NULL,
        [created_by]   INT               NULL,
        [updated_by]   INT               NULL,
        [created_at]   DATETIME2(0)      NOT NULL DEFAULT GETDATE(),
        [updated_at]   DATETIME2(0)      NOT NULL DEFAULT GETDATE(),
        CONSTRAINT [PK_allocation_list_stage] PRIMARY KEY CLUSTERED ([id] ASC)
    );
    PRINT 'Table created.';
END
ELSE
BEGIN
    PRINT 'Table already exists.';
END
EOSQL;

try {
    DB::connection('sqlsrv')->statement($sql);
    echo "Table step: OK\n";
} catch (Exception $e) {
    echo "Table step ERROR: " . $e->getMessage() . "\n";
}

// Indexes
$indexes = [
    'IX_alloc_last_name'    => "CREATE NONCLUSTERED INDEX [IX_alloc_last_name]    ON [dbo].[allocation_list_stage] ([last_name] ASC)",
    'IX_alloc_allocated_by' => "CREATE NONCLUSTERED INDEX [IX_alloc_allocated_by] ON [dbo].[allocation_list_stage] ([allocated_by] ASC)",
    'IX_alloc_created_at'   => "CREATE NONCLUSTERED INDEX [IX_alloc_created_at]   ON [dbo].[allocation_list_stage] ([created_at] DESC)",
];

foreach ($indexes as $name => $ddl) {
    $exists = DB::connection('sqlsrv')->select(
        "SELECT 1 FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID('dbo.allocation_list_stage')",
        [$name]
    );
    if (empty($exists)) {
        try {
            DB::connection('sqlsrv')->statement($ddl);
            echo "Index $name: Created\n";
        } catch (Exception $e) {
            echo "Index $name ERROR: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Index $name: Already exists\n";
    }
}

echo "Migration complete.\n";
