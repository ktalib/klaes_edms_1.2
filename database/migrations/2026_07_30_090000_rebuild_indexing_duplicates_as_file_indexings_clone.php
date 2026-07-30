<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuild indexing_duplicates as a column-for-column clone of file_indexings.
 *
 * The first version stored a hand-picked subset of fields, which meant a moved
 * record could not be lined up against the indexed files table or restored
 * faithfully. Cloning the whole row instead makes the archive a true copy: the
 * duplicates page can show the same columns as the indexed files table, and a
 * restore is a straight column-to-column insert.
 *
 * Two deliberate differences from file_indexings:
 *   - `id` is this table's own identity; the original file_indexings.id is kept
 *     in `file_indexing_id` so a moved record can still be mapped back.
 *   - every cloned column is nullable. An archive must accept whatever the source
 *     row held, including columns that were NOT NULL only because live rows
 *     always populated them (created_by, for one).
 *
 * The clone is generated from INFORMATION_SCHEMA at run time, so it matches
 * whatever shape file_indexings has in the environment being migrated.
 */
return new class extends Migration
{
    /** Columns owned by the move itself, appended after the cloned ones. */
    private const METADATA_DDL = [
        'duplicate_of'         => 'NVARCHAR(100) NULL',
        'reason'               => 'NVARCHAR(500) NULL',
        'mls_file_no_retained' => 'BIT NOT NULL CONSTRAINT DF_indexing_duplicates_mls_retained DEFAULT 0',
        'snapshot'             => 'NVARCHAR(MAX) NULL',
        'deleted_counts'       => 'NVARCHAR(MAX) NULL',
        'moved_by'             => 'NVARCHAR(150) NULL',
        'moved_by_id'          => 'BIGINT NULL',
        'moved_at'             => 'DATETIME NULL',
        'restored_at'          => 'DATETIME NULL',
        'restored_by'          => 'NVARCHAR(150) NULL',
    ];

    public function up(): void
    {
        $conn = DB::connection('sqlsrv');

        // Never silently discard archived records — they exist nowhere else.
        if (Schema::connection('sqlsrv')->hasTable('indexing_duplicates')) {
            $existing = $conn->table('indexing_duplicates')->count();
            if ($existing > 0) {
                throw new RuntimeException(
                    "indexing_duplicates already holds {$existing} archived record(s). "
                    . 'Refusing to rebuild the table automatically: back the rows up, migrate them '
                    . 'into the cloned shape, then drop the old table by hand.'
                );
            }

            Schema::connection('sqlsrv')->drop('indexing_duplicates');
        }

        $definitions = [
            '[id] BIGINT IDENTITY(1,1) NOT NULL',
            '[file_indexing_id] BIGINT NULL',
        ];

        foreach ($this->sourceColumns($conn) as $column) {
            // The source id is carried in file_indexing_id instead.
            if (strtolower($column->COLUMN_NAME) === 'id') {
                continue;
            }

            $definitions[] = sprintf('[%s] %s NULL', $column->COLUMN_NAME, $this->typeDdl($column));
        }

        foreach (self::METADATA_DDL as $name => $ddl) {
            $definitions[] = sprintf('[%s] %s', $name, $ddl);
        }

        $definitions[] = 'CONSTRAINT [PK_indexing_duplicates] PRIMARY KEY CLUSTERED ([id])';

        $conn->statement('CREATE TABLE [indexing_duplicates] (' . implode(', ', $definitions) . ')');

        $conn->statement('CREATE INDEX [idx_indexing_duplicates_file_number] ON [indexing_duplicates] ([file_number])');
        $conn->statement('CREATE INDEX [idx_indexing_duplicates_file_indexing_id] ON [indexing_duplicates] ([file_indexing_id])');
        $conn->statement('CREATE INDEX [idx_indexing_duplicates_prop_id] ON [indexing_duplicates] ([prop_id])');
        $conn->statement('CREATE INDEX [idx_indexing_duplicates_moved_at] ON [indexing_duplicates] ([moved_at])');
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('indexing_duplicates');
    }

    private function sourceColumns($conn): array
    {
        return $conn->select(
            'SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE, DATETIME_PRECISION
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION',
            ['file_indexings']
        );
    }

    /** Rebuild the SQL Server type declaration for a source column. */
    private function typeDdl(object $column): string
    {
        $type = strtolower($column->DATA_TYPE);

        switch ($type) {
            case 'nvarchar':
            case 'varchar':
            case 'nchar':
            case 'char':
            case 'varbinary':
            case 'binary':
                $length = (int) $column->CHARACTER_MAXIMUM_LENGTH;
                return strtoupper($type) . '(' . ($length === -1 ? 'MAX' : $length) . ')';

            case 'decimal':
            case 'numeric':
                return sprintf('%s(%d,%d)', strtoupper($type), $column->NUMERIC_PRECISION, $column->NUMERIC_SCALE);

            case 'datetime2':
            case 'time':
            case 'datetimeoffset':
                return sprintf('%s(%d)', strtoupper($type), (int) $column->DATETIME_PRECISION);

            default:
                // int, bigint, bit, datetime, date, float, text, uniqueidentifier, …
                return strtoupper($type);
        }
    }
};
