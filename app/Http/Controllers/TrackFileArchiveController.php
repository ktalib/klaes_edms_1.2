<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrackFileArchiveController extends Controller
{
    /**
     * Display the Track File (Archive) page.
     */
    public function index(Request $request)
    {
        $module = $request->get('url', '');
        return view('track_file_archive.index', compact('module'));
    }

    /**
     * Fetch label metadata for a given file number.
     */
    public function labelMetadata(Request $request)
    {
        $fileNumber = trim((string) $request->get('file_number', ''));

        if ($fileNumber === '') {
            return response()->json([
                'success' => false,
                'message' => 'File number is required.',
            ], 422);
        }

        $variants = $this->buildFileNumberVariants($fileNumber);
        $normalized = $this->normalizeFileno($fileNumber);

        if ($normalized !== null) {
            $variants[] = $normalized;
        }

        $variants = array_values(array_unique(array_filter($variants)));

        $normalizedVariants = array_map(static function ($value) {
            return preg_replace('/[^A-Z0-9]/', '', strtoupper($value));
        }, $variants);

        $normalizedVariants = array_values(array_unique(array_filter($normalizedVariants)));

        Log::info('Looking up label metadata', [
            'file_number' => $fileNumber,
            'variants' => $variants,
            'normalized_variants' => $normalizedVariants,
        ]);

        try {
            // Direct check for exact match first
            $directRecord = DB::connection('sqlsrv')
                ->table('print_label_batch_items')
                ->where('file_number', $fileNumber)
                ->first();

            if ($directRecord) {
                Log::info('Found direct match', ['file_number' => $fileNumber]);
                $record = $directRecord;
            } else {
                Log::info('No direct match, trying variants', ['file_number' => $fileNumber]);

                // First try exact matches with a smaller subset
                $records = DB::connection('sqlsrv')
                    ->table('print_label_batch_items')
                    ->select([
                        'id',
                        'file_number',
                        'land_use_type',
                        'shelf_location',
                        'qr_code_data',
                        'created_at',
                    ])
                    ->where(function ($query) use ($variants) {
                        foreach ($variants as $variant) {
                            $query->orWhere('file_number', 'LIKE', '%' . $variant . '%');
                        }
                    })
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get();

                // If no results, try broader search
                if ($records->isEmpty()) {
                    $records = DB::connection('sqlsrv')
                        ->table('print_label_batch_items')
                        ->select([
                            'id',
                            'file_number',
                            'land_use_type',
                            'shelf_location',
                            'qr_code_data',
                            'created_at',
                        ])
                        ->orderByDesc('id')
                        ->limit(5000)
                        ->get();
                }

                Log::info('Retrieved records count', [
                    'count' => $records->count(),
                    'search_file_number' => $fileNumber,
                    'first_few_file_numbers' => $records->take(10)->pluck('file_number')->toArray(),
                ]);

                $record = null;
                
                // First try exact match on file_number
                foreach ($records as $item) {
                    $itemFileNumber = trim((string) $item->file_number);
                    
                    // Check all variants for exact match
                    foreach ($variants as $variant) {
                        if (strcasecmp($itemFileNumber, $variant) === 0) {
                            $record = $item;
                            Log::info('Found match via variant', [
                                'item_file_number' => $itemFileNumber, 
                                'matched_variant' => $variant
                            ]);
                            break 2;
                        }
                    }
                }
            }

            if (! $record) {
                $record = $records->first(function ($item) use ($variants, $normalizedVariants) {
                    $fileNumber = strtoupper(trim((string) $item->file_number));
                    $normalizedFileNumber = preg_replace('/[^A-Z0-9]/', '', $fileNumber);

                    $payload = $this->decodeQrCodeData($item->qr_code_data);
                    $rawPayloadValues = array_filter([
                        $payload['file_number'] ?? null,
                        $payload['fileNumber'] ?? null,
                        $payload['awaiting_fileno'] ?? null,
                        $payload['awaitingFileNo'] ?? null,
                        $payload['awaitingFileNumber'] ?? null,
                    ], static function ($value) {
                        return is_string($value) && trim($value) !== '';
                    });

                    $payloadValues = array_map(static function ($value) {
                        return strtoupper(trim($value));
                    }, $rawPayloadValues);

                    $normalizedPayloadValues = array_map(static function ($value) {
                        return preg_replace('/[^A-Z0-9]/', '', $value);
                    }, $payloadValues);

                $startsWith = static function (string $haystack, string $needle): bool {
                    return strncmp($haystack, $needle, strlen($needle)) === 0;
                };

                foreach ($variants as $variant) {
                    $candidate = strtoupper(trim($variant));
                    if ($candidate === '') {
                        continue;
                    }

                    // Direct match
                    if ($fileNumber === $candidate) {
                        return true;
                    }

                    // Prefix match
                    if ($startsWith($fileNumber, $candidate . '-')) {
                        return true;
                    }

                    // Contains match (for partial file numbers)
                    if (strpos($fileNumber, $candidate) !== false) {
                        return true;
                    }

                    // Check payload
                    if (in_array($candidate, $payloadValues, true)) {
                        return true;
                    }

                    foreach ($payloadValues as $value) {
                        if ($value !== null && ($startsWith($value, $candidate . '-') || strpos($value, $candidate) !== false)) {
                            return true;
                        }
                    }
                }                    foreach ($normalizedVariants as $normalizedVariant) {
                        if ($normalizedVariant === '') {
                            continue;
                        }

                        if ($normalizedFileNumber === $normalizedVariant) {
                            return true;
                        }

                        if (in_array($normalizedVariant, $normalizedPayloadValues, true)) {
                            return true;
                        }
                    }

                    return false;
                });
            }

            if (! $record) {
                Log::info('Label metadata lookup returned no record', [
                    'file_number' => $fileNumber,
                    'variants' => $variants,
                    'normalized_variants' => $normalizedVariants,
                    'total_records_checked' => $records->count(),
                    'sample_file_numbers' => $records->take(10)->pluck('file_number')->toArray(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No label metadata found for this file number.',
                    'debug' => [
                        'searched_for' => $fileNumber,
                        'variants_tried' => $variants,
                        'total_records' => $records->count(),
                        'sample_records' => $records->take(5)->map(function($r) {
                            return ['id' => $r->id, 'file_number' => $r->file_number];
                        })->toArray()
                    ]
                ], 404);
            }

            Log::info('Found matching record', ['record_id' => $record->id, 'file_number' => $record->file_number]);

            $qrPayload = $this->decodeQrCodeData($record->qr_code_data);

            $trackingId = $qrPayload['tracking_id']
                ?? $qrPayload['trackingId']
                ?? $qrPayload['TrackingId']
                ?? null;

            $landUse = $qrPayload['land_use_type']
                ?? $qrPayload['landUse_type']
                ?? $qrPayload['landUseType']
                ?? $qrPayload['landUse']
                ?? $record->land_use_type
                ?? null;

            $shelfLocationRaw = $qrPayload['shelf_location']
                ?? $qrPayload['shelfLocation']
                ?? $qrPayload['shelf']
                ?? $record->shelf_location
                ?? null;

            $shelfLocation = $this->normalizeShelfLocation($shelfLocationRaw);

            if ($shelfLocation === '') {
                $shelfLocation = null;
            }

            $shelf = null;
            $rack = null;

            if ($shelfLocation !== null) {
                $shelf = substr($shelfLocation, 0, 1) ?: null;
                $rack = strlen($shelfLocation) > 1 ? substr($shelfLocation, 1) : null;

                if ($rack === '') {
                    $rack = null;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'file_number' => $record->file_number,
                    'tracking_id' => $trackingId,
                    'land_use_type' => $landUse,
                    'land_use_type_label' => $landUse,
                    'shelf' => $shelf,
                    'rack' => $rack,
                    'shelf_location' => $shelfLocation,
                    'shelf_location_label' => $shelfLocationRaw,
                    'qr_code_payload' => $qrPayload,
                    'record_id' => $record->id,
                    'raw_record' => (array) $record,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('TrackFileArchiveController::labelMetadata failed', [
                'file_number' => $fileNumber,
                'variants' => $variants,
                'normalized_variants' => $normalizedVariants,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve label metadata for the supplied file number.',
            ], 500);
        }
    }

    /**
     * Normalize a file number by removing separators.
     */
    protected function normalizeFileno(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(trim($value));

        return str_replace(['-', '/', ' ', '\\', '.', ','], '', $normalized);
    }

    /**
     * Build file number variants to improve matching accuracy.
     */
    protected function buildFileNumberVariants(string $fileNumber): array
    {
        $normalized = strtoupper(trim($fileNumber));
        $variants = [$normalized];

        // Remove spaces
        $variants[] = str_replace(' ', '', $normalized);

        // Handle known prefixes (e.g., ST-RES-..., NPFN-RES-...)
        if (preg_match('/^(ST|NPFN|NP|FN|MLS|MLSF|NEWKANGIS|NK)-?(.*)$/', $normalized, $prefixMatches)) {
            $suffix = $prefixMatches[2] ?? '';
            if ($suffix !== '') {
                $variants[] = $suffix;
                $variants[] = str_replace(' ', '', $suffix);
                $variants[] = str_replace('-', '', $suffix);
            }
        }

        // Handle CON-prefixed formats
        if (preg_match('/^(CO?N?-?)([A-Z]{3})-(\d{4})-(\d+)$/', $normalized, $matches)) {
            $prefix = $matches[1];
            $landUse = $matches[2];
            $year = $matches[3];
            $serial = ltrim($matches[4], '0');

            if ($serial !== '') {
                $variants[] = sprintf('%s-%s-%s', $landUse, $year, $serial);
                $variants[] = sprintf('%s-%s-%04d', $landUse, $year, (int) $serial);

                $variants[] = sprintf('%s%s-%s-%s', $prefix, $landUse, $year, $serial);
                $variants[] = sprintf('%s%s-%s-%04d', $prefix, $landUse, $year, (int) $serial);
                $variants[] = sprintf('CON-%s-%s-%s', $landUse, $year, $serial);
                $variants[] = sprintf('CON-%s-%s-%04d', $landUse, $year, (int) $serial);
                $variants[] = sprintf('CON%s-%s-%s', $landUse, $year, $serial);
                $variants[] = sprintf('CON%s-%s-%04d', $landUse, $year, (int) $serial);
                $variants[] = sprintf('CN-%s-%s-%s', $landUse, $year, $serial);
                $variants[] = sprintf('CN-%s-%s-%04d', $landUse, $year, (int) $serial);
                $variants[] = sprintf('C-%s-%s-%s', $landUse, $year, $serial);
                $variants[] = sprintf('C-%s-%s-%04d', $landUse, $year, (int) $serial);
            }
        }

        // Handle standard format
        if (preg_match('/^([A-Z]{3})-(\d{4})-(\d+)$/', $normalized, $matches)) {
            $landUse = $matches[1];
            $year = $matches[2];
            $serial = ltrim($matches[3], '0');

            if ($serial !== '') {
                $variants[] = sprintf('%s-%s-%s', $landUse, $year, $serial);
                $variants[] = sprintf('%s-%s-%04d', $landUse, $year, (int) $serial);
                $variants[] = sprintf('CON-%s-%s-%s', $landUse, $year, $serial);
                $variants[] = sprintf('CON-%s-%s-%04d', $landUse, $year, (int) $serial);
                $variants[] = sprintf('CON%s-%s-%s', $landUse, $year, $serial);
                $variants[] = sprintf('CON%s-%s-%04d', $landUse, $year, (int) $serial);
            }
        }

        // Handle unit suffixes (e.g., ST-RES-2025-1-001)
        $parts = explode('-', $normalized);
        if (count($parts) >= 4) {
            $primaryCandidate = implode('-', array_slice($parts, 0, 3));
            $variants[] = $primaryCandidate;

            if (preg_match('/^([A-Z]{3})-(\d{4})-(\d+)$/', $primaryCandidate, $primaryMatches)) {
                $landUse = $primaryMatches[1];
                $year = $primaryMatches[2];
                $serial = ltrim($primaryMatches[3], '0');

                if ($serial !== '') {
                    $variants[] = sprintf('%s-%s-%s', $landUse, $year, $serial);
                    $variants[] = sprintf('%s-%s-%04d', $landUse, $year, (int) $serial);
                    $variants[] = sprintf('CON-%s-%s-%s', $landUse, $year, $serial);
                    $variants[] = sprintf('CON-%s-%s-%04d', $landUse, $year, (int) $serial);
                }
            }
        }

        // Remove all separators variant
        $variants[] = str_replace('-', '', $normalized);

        if (strpos($normalized, '-') !== false) {
            $variants[] = str_replace('-', '_', $normalized);
            $variants[] = str_replace('-', '/', $normalized);
            $variants[] = str_replace('-', ' ', $normalized);
        }

        return array_values(array_unique(array_filter($variants)));
    }

    /**
     * Decode QR code payload from print label items.
     */
    protected function decodeQrCodeData($value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return [];
        }

        $attempts = [
            $stringValue,
            stripslashes($stringValue),
        ];

        // Handle JSON quoted as a string ("{...}")
        if ($stringValue[0] === '"' && substr($stringValue, -1) === '"') {
            $attempts[] = trim($stringValue, '"');
        }

        foreach ($attempts as $candidate) {
            $decoded = json_decode($candidate, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        Log::warning('Unable to decode qr_code_data payload', [
            'payload_preview' => mb_substr($stringValue, 0, 150),
            'json_error' => json_last_error_msg(),
        ]);

        return [];
    }

    /**
     * Normalize shelf location string (e.g. "A1" or "A-1" -> "A1").
     */
    protected function normalizeShelfLocation($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return null;
        }

        // Remove common separators while preserving leading character-order semantics
        $compact = strtoupper(str_replace(['-', ' ', '/', '\\'], '', $stringValue));

        return $compact === '' ? null : $compact;
    }

    /**
     * Escape SQL LIKE special characters.
     */
    protected function escapeLike(string $value): string
    {
        return str_replace(
            ['%', '_', '['],
            ['[%]', '[_]', '[[]'],
            $value
        );
    }
}
