<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add department membership flags to pp_inspectors so a single inspector
     * can serve Physical Planning, Sectional Titling, or both. Seed the
     * Sectional Titling roster requested for the ST Unit JSI.
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('pp_inspectors', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('pp_inspectors', 'in_physical_planning')) {
                $table->boolean('in_physical_planning')->default(true)->after('rank')
                    ->comment('Inspector serves the Physical Planning department');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('pp_inspectors', 'in_sectional_titling')) {
                $table->boolean('in_sectional_titling')->default(false)->after('in_physical_planning')
                    ->comment('Inspector serves the Sectional Titling department');
            }
        });

        $conn = DB::connection('sqlsrv');

        // Every existing inspector belongs to Physical Planning.
        $conn->table('pp_inspectors')->update(['in_physical_planning' => 1]);

        // These two already exist under Physical Planning and must ALSO appear
        // under Sectional Titling, using the ranks supplied for the ST roster.
        $conn->table('pp_inspectors')
            ->whereRaw('UPPER(LTRIM(RTRIM(name))) LIKE ?', ['%UMAR%ADAMU%MUHAMMAD%'])
            ->update(['in_sectional_titling' => 1, 'rank' => 'Engineer']);

        $conn->table('pp_inspectors')
            ->whereRaw('UPPER(LTRIM(RTRIM(name))) LIKE ?', ['%ZAKARI%SUL%SALISU%'])
            ->update(['in_sectional_titling' => 1, 'rank' => 'TPL']);

        // Sectional Titling only inspectors.
        $now = now();
        $newInspectors = [
            ['name' => 'ILYASU SULEIMAN AMINU', 'rank' => 'TPL'],
            ['name' => 'USMAN IBRAHIM DANKWANO', 'rank' => 'Engineer'],
            ['name' => 'ABBA IBRAHIM INUWA', 'rank' => 'Staff'],
        ];

        foreach ($newInspectors as $inspector) {
            $exists = $conn->table('pp_inspectors')
                ->whereRaw('UPPER(LTRIM(RTRIM(name))) = ?', [$inspector['name']])
                ->exists();

            if (!$exists) {
                $conn->table('pp_inspectors')->insert([
                    'name' => $inspector['name'],
                    'rank' => $inspector['rank'],
                    'in_physical_planning' => 0,
                    'in_sectional_titling' => 1,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Cache::forget('pp_inspectors_all');
    }

    public function down(): void
    {
        $conn = DB::connection('sqlsrv');

        // Remove the Sectional Titling only inspectors added by this migration.
        $conn->table('pp_inspectors')
            ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(name)))'), [
                'ILYASU SULEIMAN AMINU',
                'USMAN IBRAHIM DANKWANO',
                'ABBA IBRAHIM INUWA',
            ])
            ->delete();

        Schema::connection('sqlsrv')->table('pp_inspectors', function (Blueprint $table) {
            foreach (['in_sectional_titling', 'in_physical_planning'] as $column) {
                if (Schema::connection('sqlsrv')->hasColumn('pp_inspectors', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Cache::forget('pp_inspectors_all');
    }
};
