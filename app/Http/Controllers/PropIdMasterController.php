<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PropIdMasterController extends Controller
{
    public function index(Request $request)
    {
        $connection = DB::connection('sqlsrv');
        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 25);

        if ($perPage <= 0 || $perPage > 100) {
            $perPage = 25;
        }

        $columnSets = [
            'file_history_staging' => ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'],
            'pra' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'],
            'pic' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'],
            'CofO_staging' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'np_fileno', 'temp_fileno'],
        ];

        $availableColumns = [];
        foreach ($columnSets as $table => $columns) {
            $availableColumns[$table] = $this->resolveExistingColumns($table, $columns);
        }

        $query = $connection
            ->table('PropID_Master as pm')
            ->select([
                'pm.id',
                'pm.prop_id',
                'pm.primary_file_number',
                'pm.mlsFNo',
                'pm.kangisFileNo',
                'pm.NewKANGISFileno',
                'pm.temp_fileno',
                'pm.source_table',
                'pm.status',
                'pm.created_at',
                'pm.updated_at',
            ])
            ->selectRaw($this->linkCountSubquery(
                'file_history_staging',
                'fh',
                'file_history_links',
                $availableColumns['file_history_staging']
            ))
            ->selectRaw($this->linkCountSubquery(
                'pra',
                'pra',
                'pra_links',
                $availableColumns['pra']
            ))
            ->selectRaw($this->linkCountSubquery(
                'pic',
                'pic',
                'pic_links',
                $availableColumns['pic']
            ))
            ->selectRaw($this->linkCountSubquery(
                'CofO_staging',
                'co',
                'cofo_links',
                $availableColumns['CofO_staging']
            ));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                if (is_numeric($search)) {
                    $builder->orWhere('pm.prop_id', (int) $search);
                }

                $like = '%' . $search . '%';

                $builder->orWhere('pm.primary_file_number', 'LIKE', $like)
                    ->orWhere('pm.mlsFNo', 'LIKE', $like)
                    ->orWhere('pm.kangisFileNo', 'LIKE', $like)
                    ->orWhere('pm.NewKANGISFileno', 'LIKE', $like)
                    ->orWhere('pm.temp_fileno', 'LIKE', $like)
                    ->orWhere('pm.source_table', 'LIKE', $like);
            });
        }

        $records = $query
            ->orderByDesc('pm.updated_at')
            ->orderByDesc('pm.created_at')
            ->paginate($perPage)
            ->withQueryString();

        try {
            $conflicts = (int) $connection->table(DB::raw('vw_prop_id_conflicts'))->count();
        } catch (\Throwable $exception) {
            $conflicts = 0;
        }

        $metrics = [
            'total_master' => (int) $connection->table('PropID_Master')->count(),
            'with_official_file' => (int) $connection->table('PropID_Master')
                ->where(function ($builder) {
                    $builder->whereNotNull('primary_file_number')
                        ->orWhereNotNull('mlsFNo')
                        ->orWhereNotNull('kangisFileNo')
                        ->orWhereNotNull('NewKANGISFileno');
                })
                ->count(),
            'temp_only' => (int) $connection->table('PropID_Master')
                ->whereNull('primary_file_number')
                ->whereNull('mlsFNo')
                ->whereNull('kangisFileNo')
                ->whereNull('NewKANGISFileno')
                ->count(),
            'conflicts' => $conflicts,
        ];

        return view('propid.master_dashboard', [
            'records' => $records,
            'search' => $search,
            'perPage' => $perPage,
            'metrics' => $metrics,
            'PageTitle' => 'PropID Master Dashboard',
        ]);
    }

    /**
     * Build a COUNT(*) subquery that links a downstream table back to the master file number.
     */
    private function linkCountSubquery(string $table, string $alias, string $selectAlias, array $columns): string
    {
        $propMatch = "{$alias}.prop_id = pm.prop_id";
        $normalized = $this->buildNormalizedExpression($alias, $columns);

        if ($normalized === null) {
            return "(SELECT COUNT(*) FROM {$table} {$alias} WHERE {$propMatch}) AS {$selectAlias}";
        }

        return "(SELECT COUNT(*) FROM {$table} {$alias} WHERE {$propMatch} OR {$normalized} = pm.primary_file_number_norm) AS {$selectAlias}";
    }

    private function buildNormalizedExpression(string $alias, array $columns): ?string
    {
        if (empty($columns)) {
            return null;
        }

        $qualified = array_map(fn ($column) => "{$alias}." . $column, $columns);

        $valueExpression = count($qualified) === 1
            ? $qualified[0]
            : 'COALESCE(' . implode(', ', $qualified) . ')';

        return "UPPER(LTRIM(RTRIM({$valueExpression})))";
    }

    private function resolveExistingColumns(string $table, array $columns): array
    {
        return array_values(array_filter($columns, function ($column) use ($table) {
            try {
                return Schema::connection('sqlsrv')->hasColumn($table, $column);
            } catch (\Throwable $exception) {
                return false;
            }
        }));
    }
}
