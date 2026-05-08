<?php

namespace App\Services;

use App\Exceptions\PropIdAllocationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PropertyIdAllocationService
{
    private const MASTER_IDENTIFIER_KEYS = [
        'primary_file_number',
        'mlsFNo',
        'kangisFileNo',
        'NewKANGISFileno',
        'temp_fileno',
    ];

    private ?bool $propIdMasterExists = null;

    /**
     * Allocate (or reuse) a prop_id for the provided file numbers.
   
     */
    public function allocateOrRetrievePropId(
        ?string $primaryFileNumber,
        ?string $mlsFNo = null,
        ?string $kangisFileNo = null,
        ?string $newKangisFileNo = null,
        array $options = []
    ): int {
        $allowTempOnly = (bool) ($options['allow_temp_only'] ?? false);
        $skipLookup = (bool) ($options['skip_lookup'] ?? false);

        $identifierMap = $this->buildIdentifierMap(
            $primaryFileNumber,
            $mlsFNo,
            $kangisFileNo,
            $newKangisFileNo,
            $options
        );

        $searchableIdentifiers = $this->normalizeIdentifiers(
            array_filter($identifierMap, fn ($value) => $value !== null)
        );

        if (empty($searchableIdentifiers)) {
            throw PropIdAllocationException::missingIdentifiers();
        }

        return DB::connection('sqlsrv')->transaction(function () use ($identifierMap, $searchableIdentifiers, $skipLookup, $allowTempOnly) {
            if (!$skipLookup) {
                $masterRow = $this->findMasterRowByPrimary($identifierMap['primary_file_number'] ?? null)
                    ?? $this->findMasterRow($identifierMap);
                if ($masterRow !== null) {
                    $this->fillMissingIdentifiersInMaster($masterRow, $identifierMap);

                    return (int) $masterRow->prop_id;
                }

                $existing = $this->findExistingPropId($searchableIdentifiers);
                if ($existing !== null) {
                    $this->ensureMasterRowForPropId($existing, $identifierMap);

                    return $existing;
                }
            }

            if (!$allowTempOnly && !$this->hasOfficialIdentifiers($identifierMap)) {
                throw PropIdAllocationException::officialNumberRequired();
            }

            // skip_lookup means the caller (e.g. an OP->ToT mint) wants a brand-
            // new prop_id even though a PropID_Master row already maps this file
            // number to the source row's prop_id. Inserting another master row
            // here would violate the unique index on primary_file_number, so we
            // just allocate the next sequential prop_id and let the caller persist
            // it directly on its row (no master mutation needed - the existing
            // master entry continues to point to the source/OP prop_id).
            if ($skipLookup) {
                $freshId = $this->generateNextPropIdFromMaster();

                // We MUST record this in Master to prevent the next call from getting the same ID.
                // If the primary file number is already taken, use the temp_fileno as the primary key.
                $masterPrimary = $identifierMap['primary_file_number'];
                if ($this->findMasterRowByPrimary($masterPrimary)) {
                    $masterPrimary = $identifierMap['temp_fileno'] ?? ($masterPrimary . '-REF-' . $freshId);
                }

                $this->insertMasterRow($freshId, $identifierMap, $masterPrimary);
                return $freshId;
            }

            return $this->createPropIdViaMaster($identifierMap);
        });
    }

    /**
     * Update file_history_staging with the supplied prop_id for a file number.
     */
    public function syncPropIdToFileHistory(string $fileNumber, int $propId, ?string $testControl = null): void
    {
        $normalizedFileNumber = $this->normalizeValue($fileNumber);
        if ($normalizedFileNumber === null) {
            return;
        }

        $payload = [
            'prop_id' => $propId,
            'updated_at' => now(),
        ];

        if ($testControl !== null) {
            $payload['test_control'] = $testControl;
        }

        try {
            DB::connection('sqlsrv')
                ->table('file_history_staging')
                ->where(function ($query) use ($normalizedFileNumber) {
                    $query->where('mlsFNo', $normalizedFileNumber)
                        ->orWhere('fileno', $normalizedFileNumber);
                })
                ->update($payload);
        } catch (\Throwable $exception) {
            Log::warning('PropertyIdAllocationService::syncPropIdToFileHistory failed', [
                'file_number' => $fileNumber,
                'prop_id' => $propId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function buildIdentifierMap(
        ?string $primaryFileNumber,
        ?string $mlsFNo,
        ?string $kangisFileNo,
        ?string $newKangisFileNo,
        array $options
    ): array {
        $normalizedPrimary = $this->normalizeValue($primaryFileNumber);
        $normalizedMls = $this->normalizeValue($mlsFNo);
        $normalizedKangis = $this->normalizeValue($kangisFileNo);
        $normalizedNewKangis = $this->normalizeValue($newKangisFileNo);

        if ($normalizedPrimary === null && $normalizedMls !== null) {
            $normalizedPrimary = $normalizedMls;
        }

        return [
            'primary_file_number' => $normalizedPrimary,
            'mlsFNo' => $normalizedMls,
            'kangisFileNo' => $normalizedKangis,
            'NewKANGISFileno' => $normalizedNewKangis,
            'temp_fileno' => $this->normalizeValue($options['temp_fileno'] ?? null),
        ];
    }

    private function hasOfficialIdentifiers(array $identifierMap): bool
    {
        $officialValues = array_values(array_filter([
            $identifierMap['primary_file_number'] ?? null,
            $identifierMap['mlsFNo'] ?? null,
            $identifierMap['kangisFileNo'] ?? null,
            $identifierMap['NewKANGISFileno'] ?? null,
        ]));

        if (empty($officialValues)) {
            return false;
        }

        $tempValue = $identifierMap['temp_fileno'] ?? null;
        if ($tempValue !== null && count($officialValues) === 1 && $officialValues[0] === $tempValue) {
            return false;
        }

        return true;
    }

    private function propIdMasterTableExists(): bool
    {
        if ($this->propIdMasterExists !== null) {
            return $this->propIdMasterExists;
        }

        try {
            $this->propIdMasterExists = Schema::connection('sqlsrv')->hasTable('PropID_Master');
        } catch (\Throwable $exception) {
            Log::warning('PropertyIdAllocationService: unable to detect PropID_Master table', [
                'error' => $exception->getMessage(),
            ]);

            $this->propIdMasterExists = false;
        }

        return $this->propIdMasterExists;
    }

    private function findMasterRow(array $identifierMap): ?object
    {
        if (!$this->propIdMasterTableExists()) {
            return null;
        }

        $searchable = $this->normalizeIdentifiers(
            array_filter($identifierMap, fn ($value) => $value !== null)
        );

        if (empty($searchable)) {
            return null;
        }

        $columns = [
            'primary_file_number_norm',
            'mlsFNo_norm',
            'kangisFileNo_norm',
            'NewKANGISFileno_norm',
            'temp_fileno_norm',
            'primary_file_number',
            'mlsFNo',
            'kangisFileNo',
            'NewKANGISFileno',
            'temp_fileno',
        ];

        $query = DB::connection('sqlsrv')
            ->table('PropID_Master')
            ->select('id', 'prop_id', 'primary_file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno', 'updated_at')
            ->where(function ($builder) use ($columns, $searchable) {
                foreach ($columns as $column) {
                    foreach ($searchable as $identifier) {
                        $builder->orWhere($column, $identifier);
                    }
                }
            })
            ->orderByDesc('updated_at')
            ->limit(1);

        $row = $query->first();

        return $row ?: null;
    }

    private function fillMissingIdentifiersInMaster(object $existingRow, array $identifierMap): void
    {
        if (!$this->propIdMasterTableExists()) {
            return;
        }

        $updates = $this->calculateMasterUpdates($existingRow, $identifierMap);

        $preferredPrimary = $this->resolvePrimaryIdentifier($identifierMap);
        $currentPrimary = $existingRow->primary_file_number ?? null;

        if ($preferredPrimary !== null && ($currentPrimary === null || trim((string) $currentPrimary) === '')) {
            $conflictingPrimaryRow = $this->findMasterRowByPrimary($preferredPrimary);

            if ($conflictingPrimaryRow !== null && (int) $conflictingPrimaryRow->id !== (int) $existingRow->id) {
                Log::warning('PropertyIdAllocationService: skipped primary file number update due to unique index conflict', [
                    'target_row_id' => $existingRow->id ?? null,
                    'conflicting_row_id' => $conflictingPrimaryRow->id ?? null,
                    'preferred_primary' => $preferredPrimary,
                    'target_prop_id' => $existingRow->prop_id ?? null,
                    'conflicting_prop_id' => $conflictingPrimaryRow->prop_id ?? null,
                ]);
            } else {
                $updates['primary_file_number'] = $preferredPrimary;
            }
        }

        if (empty($updates)) {
            return;
        }

        $updates['updated_at'] = now();

        Log::info('PropertyIdAllocationService: updating PropID_Master', [
            'id' => $existingRow->id,
            'updates' => $updates
        ]);

        DB::connection('sqlsrv')
            ->table('PropID_Master')
            ->where('id', $existingRow->id)
            ->update($updates);
    }

    private function findMasterRowByPrimary(?string $primaryFileNumber): ?object
    {
        if (!$this->propIdMasterTableExists()) {
            return null;
        }

        $normalizedPrimary = $this->normalizeValue($primaryFileNumber);
        if ($normalizedPrimary === null) {
            return null;
        }

        $row = DB::connection('sqlsrv')
            ->table('PropID_Master')
            ->select('id', 'prop_id', 'primary_file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno', 'updated_at')
            ->where(function ($q) use ($normalizedPrimary, $primaryFileNumber) {
                $q->where('primary_file_number_norm', $normalizedPrimary)
                    ->orWhere('primary_file_number', $primaryFileNumber);
            })
            ->orderByDesc('updated_at')
            ->first();

        return $row ?: null;
    }

    private function calculateMasterUpdates(object $existingRow, array $identifierMap): array
    {
        $updates = [];

        foreach (self::MASTER_IDENTIFIER_KEYS as $key) {
            $newValue = $identifierMap[$key] ?? null;
            if ($newValue === null) {
                continue;
            }

            $currentValue = $existingRow->{$key} ?? null;
            if ($currentValue === null) {
                $updates[$key] = $newValue;
                continue;
            }

            if ($key === 'primary_file_number' && $currentValue !== $newValue) {
                Log::warning('PropertyIdAllocationService: primary file number mismatch detected', [
                    'existing' => $currentValue,
                    'incoming' => $newValue,
                    'prop_id' => $existingRow->prop_id ?? null,
                ]);
            }
        }

        return $updates;
    }

    private function ensureMasterRowForPropId(int $propId, array $identifierMap): void
    {
        if (!$this->propIdMasterTableExists()) {
            return;
        }

        $primaryForMaster = $this->resolvePrimaryIdentifier($identifierMap);
        if ($primaryForMaster === null) {
            return;
        }

        $table = DB::connection('sqlsrv')->table('PropID_Master');
        $existing = $table->where('prop_id', $propId)->first();

        if ($existing) {
            $this->fillMissingIdentifiersInMaster($existing, $identifierMap);

            return;
        }

        $this->insertMasterRow($propId, $identifierMap, $primaryForMaster);
    }

    private function createPropIdViaMaster(array $identifierMap): int
    {
        if (!$this->propIdMasterTableExists()) {
            return $this->generateNextPropId();
        }

        return DB::connection('sqlsrv')->transaction(function () use ($identifierMap) {
            $primaryForMaster = $this->resolvePrimaryIdentifier($identifierMap);
            if ($primaryForMaster === null) {
                throw PropIdAllocationException::officialNumberRequired();
            }

            $propId = $this->generateNextPropIdFromMaster();

            $this->insertMasterRow($propId, $identifierMap, $primaryForMaster);

            return $propId;
        });
    }

    private function insertMasterRow(int $propId, array $identifierMap, string $primaryForMaster): void
    {
        $timestamp = now();

        // Prevent unique constraint violations and ensure search integrity by
        // 'stealing' conflicting identifiers from older master rows. This ensures
        // the latest property entity (e.g. a ToT) is the one discovered by file no.
        $conflicts = [
            'mlsFNo' => $identifierMap['mlsFNo'] ?? null,
            'kangisFileNo' => $identifierMap['kangisFileNo'] ?? null,
            'NewKANGISFileno' => $identifierMap['NewKANGISFileno'] ?? null,
        ];

        foreach ($conflicts as $column => $value) {
            if (!$value) continue;
            
            DB::connection('sqlsrv')->table('PropID_Master')
                ->where($column, $value)
                ->where('prop_id', '!=', $propId)
                ->update([
                    $column => null,
                    'updated_at' => $timestamp
                ]);
        }

        DB::connection('sqlsrv')
            ->table('PropID_Master')
            ->insert([
                'prop_id' => $propId,
                'primary_file_number' => $primaryForMaster,
                'mlsFNo' => $identifierMap['mlsFNo'] ?? null,
                'kangisFileNo' => $identifierMap['kangisFileNo'] ?? null,
                'NewKANGISFileno' => $identifierMap['NewKANGISFileno'] ?? null,
                'temp_fileno' => $identifierMap['temp_fileno'] ?? null,
                'status' => 'active',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
    }

    private function resolvePrimaryIdentifier(array $identifierMap): ?string
    {
        foreach (['mlsFNo', 'primary_file_number', 'kangisFileNo', 'NewKANGISFileno'] as $key) {
            if (!empty($identifierMap[$key])) {
                return $identifierMap[$key];
            }
        }

        return null;
    }

    /**
     * Look for an existing prop_id in legacy tables (outside PropID_Master).
     */
    private function findExistingPropId(array $normalizedIdentifiers): ?int
    {
        $propertyTables = [
            'file_history_staging' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno', 'temp_fileno'],
            'pra' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'],
            'pic' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'],
            'CofO_staging' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'np_fileno', 'temp_fileno'],
        ];

        $grammar = DB::connection('sqlsrv')->getQueryGrammar();

        foreach ($propertyTables as $table => $columns) {
            $availableColumns = array_values(array_filter($columns, function ($column) use ($table) {
                try {
                    return Schema::connection('sqlsrv')->hasColumn($table, $column);
                } catch (\Throwable $e) {
                    Log::warning('PropertyIdAllocationService missing column during lookup', [
                        'table' => $table,
                        'column' => $column,
                        'error' => $e->getMessage(),
                    ]);

                    return false;
                }
            }));

            if (empty($availableColumns)) {
                continue;
            }

            $query = DB::connection('sqlsrv')
                ->table($table)
                ->select('prop_id')
                ->whereNotNull('prop_id');

            if (!empty($normalizedIdentifiers)) {
                // Determine if we have any official identifiers to prioritize.
                $hasOfficial = false;
                foreach (['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno', 'np_fileno'] as $official) {
                    if (!empty($normalizedIdentifiers[$official])) {
                        $hasOfficial = true;
                        break;
                    }
                }

                $query->where(function ($builder) use ($availableColumns, $normalizedIdentifiers, $grammar, $hasOfficial) {
                    foreach ($availableColumns as $column) {
                        // Skip temp_fileno if we have official IDs to prevent accidental collisions.
                        if ($column === 'temp_fileno' && $hasOfficial) {
                            continue;
                        }

                        foreach ($normalizedIdentifiers as $identifier) {
                            $builder->orWhereRaw('UPPER(' . $grammar->wrap($column) . ') = ?', [$identifier]);
                        }
                    }
                });
            }

            $existing = $query->orderByDesc('id')->first();
            if ($existing && $existing->prop_id) {
                return (int) $existing->prop_id;
            }
        }

        return null;
    }

    /**
     * Compute the next available prop_id.
     */
    private function generateNextPropId(): int
    {
        if ($this->propIdMasterTableExists()) {
            return DB::connection('sqlsrv')->transaction(function () {
                return $this->generateNextPropIdFromMaster();
            });
        }

        $candidates = [
            (int) DB::connection('sqlsrv')->table('file_history_staging')->max('prop_id'),
            (int) DB::connection('sqlsrv')->table('pra')->max('prop_id'),
            (int) DB::connection('sqlsrv')->table('pic')->max('prop_id'),
            (int) DB::connection('sqlsrv')->table('CofO_staging')->max('prop_id'),
        ];

        $max = collect($candidates)->filter(fn ($value) => $value > 0)->max();

        return $max ? $max + 1 : 1;
    }

    private function generateNextPropIdFromMaster(): int
    {
        $max = DB::connection('sqlsrv')
            ->table('PropID_Master')
            ->lockForUpdate()
            ->where('prop_id', '<', 2147483647) // Ensure we stay within 32-bit int range for this table
            ->orderByDesc('prop_id')
            ->value('prop_id');

        return $max ? ((int) $max + 1) : 1;
    }

    /**
     * Normalize input identifiers by trimming/uppercasing.
     */
    private function normalizeIdentifiers(array $identifiers): array
    {
        return array_values(array_filter(array_unique(array_map(function ($value) {
            return $this->normalizeValue($value);
        }, $identifiers))));
    }

    private function normalizeValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = is_string($value) ? trim($value) : trim((string) $value);
        $upperValue = strtoupper($stringValue);

        // Ignore common placeholder strings that cause mass collisions
        $invalidPlaceholders = ['N/A', 'NA', 'NONE', 'NULL', 'NIL', '-', '.', '0', 'TBD'];
        if ($upperValue === '' || in_array($upperValue, $invalidPlaceholders, true)) {
            return null;
        }

        return $upperValue;
    }
}
