<?php

use App\Support\SltrDigitRank;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('file_indexings') && !$schema->hasColumn('file_indexings', 'digit_rank')) {
            $schema->table('file_indexings', function (Blueprint $table) {
                $table->unsignedTinyInteger('digit_rank')->nullable()->after('suffix');
            });
        }

        if ($schema->hasTable('sltr_grouping') && !$schema->hasColumn('sltr_grouping', 'digit_rank')) {
            $schema->table('sltr_grouping', function (Blueprint $table) {
                $table->unsignedTinyInteger('digit_rank')->nullable()->after('sltr_awaiting_fileno');
            });
        }

        $this->backfillDigitRank('file_indexings', 'file_number');
        $this->backfillDigitRank('sltr_grouping', 'sltr_awaiting_fileno');

        if ($schema->hasTable('file_indexings')) {
            DB::connection('sqlsrv')->statement("
                IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_file_indexings_digit_rank' AND object_id = OBJECT_ID('[dbo].[file_indexings]'))
                CREATE NONCLUSTERED INDEX [idx_file_indexings_digit_rank] ON [dbo].[file_indexings] ([digit_rank], [file_number])
            ");
        }

        if ($schema->hasTable('sltr_grouping')) {
            DB::connection('sqlsrv')->statement("
                IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_sltr_grouping_digit_rank' AND object_id = OBJECT_ID('[dbo].[sltr_grouping]'))
                CREATE NONCLUSTERED INDEX [idx_sltr_grouping_digit_rank] ON [dbo].[sltr_grouping] ([digit_rank], [sltr_awaiting_fileno])
            ");
        }
    }

    public function down(): void
    {
        DB::connection('sqlsrv')->statement("
            IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_file_indexings_digit_rank' AND object_id = OBJECT_ID('[dbo].[file_indexings]'))
            DROP INDEX [idx_file_indexings_digit_rank] ON [dbo].[file_indexings]
        ");

        DB::connection('sqlsrv')->statement("
            IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_sltr_grouping_digit_rank' AND object_id = OBJECT_ID('[dbo].[sltr_grouping]'))
            DROP INDEX [idx_sltr_grouping_digit_rank] ON [dbo].[sltr_grouping]
        ");

        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('sltr_grouping') && $schema->hasColumn('sltr_grouping', 'digit_rank')) {
            $schema->table('sltr_grouping', function (Blueprint $table) {
                $table->dropColumn('digit_rank');
            });
        }

        if ($schema->hasTable('file_indexings') && $schema->hasColumn('file_indexings', 'digit_rank')) {
            $schema->table('file_indexings', function (Blueprint $table) {
                $table->dropColumn('digit_rank');
            });
        }
    }

    private function backfillDigitRank(string $table, string $fileNumberColumn): void
    {
        if (!Schema::connection('sqlsrv')->hasTable($table) || !Schema::connection('sqlsrv')->hasColumn($table, 'digit_rank')) {
            return;
        }

        DB::connection('sqlsrv')
            ->table($table)
            ->select(['id', $fileNumberColumn, 'digit_rank'])
            ->where(function ($query) use ($fileNumberColumn) {
                $query->where($fileNumberColumn, 'like', '%SLTR%');
            })
            ->chunkById(500, function ($rows) use ($table, $fileNumberColumn) {
                foreach ($rows as $row) {
                    $rank = SltrDigitRank::fromFileNumber($row->{$fileNumberColumn} ?? null);

                    if ((int) ($row->digit_rank ?? 0) === (int) ($rank ?? 0)) {
                        continue;
                    }

                    DB::connection('sqlsrv')
                        ->table($table)
                        ->where('id', $row->id)
                        ->update(['digit_rank' => $rank]);
                }
            });
    }
};
