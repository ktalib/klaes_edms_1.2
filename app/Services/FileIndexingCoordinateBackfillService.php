<?php

namespace App\Services;

use App\Models\FileIndexing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class FileIndexingCoordinateBackfillService
{
    /** @var array<string, array{lat: float, lng: float}|null> */
    private array $cache = [];

    /** @var array<string, string> district id => name */
    private array $districtNames = [];

    /** @var array<string, string> lga id => name */
    private array $lgaNames = [];

    public function remainingCount(bool $force = false): int
    {
        $query = FileIndexing::on('sqlsrv');
        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('latitude')->orWhereNull('longitude');
            });
        }
        return $query->count();
    }

    /**
     * Geocode up to $limit rows (with id > $afterId) and write back latitude/longitude.
     * The $afterId cursor is required so rows that get SKIPPED_NO_ADDRESS (never
     * written to) don't keep getting re-selected as the "first N missing coords"
     * forever — the caller advances $afterId to the batch's last id each call.
     *
     * @return array{key_missing?: bool, processed: int, written: int, remaining: int, last_id: ?int, counts: array<string, int>}
     */
    public function runBatch(int $limit, bool $dryRun = false, bool $force = false, ?int $afterId = null): array
    {
        $key = config('services.google_maps.geocoding_key');
        if (empty($key)) {
            return ['key_missing' => true, 'processed' => 0, 'written' => 0, 'remaining' => $this->remainingCount($force), 'last_id' => $afterId, 'counts' => []];
        }

        $this->districtNames = DB::connection('sqlsrv')->table('districts')->pluck('name', 'id')->all();
        $this->lgaNames = DB::connection('sqlsrv')->table('lgas')->pluck('name', 'id')->all();

        $query = FileIndexing::on('sqlsrv')
            ->select(['id', 'plot_number', 'street_name', 'district', 'lga', 'latitude', 'longitude']);

        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('latitude')->orWhereNull('longitude');
            });
        }

        if ($afterId !== null) {
            $query->where('id', '>', $afterId);
        }

        $rows = $query->orderBy('id')->limit($limit)->get();

        $counts = [];
        $written = 0;
        $lastId = $afterId;

        foreach ($rows as $row) {
            $lastId = $row->id;
            $address = $this->buildGeocodeAddress($row);

            if ($address === null) {
                $counts['SKIPPED_NO_ADDRESS'] = ($counts['SKIPPED_NO_ADDRESS'] ?? 0) + 1;
                continue;
            }

            $result = $this->geocode($address, $key);
            $status = $result['status'];
            $counts[$status] = ($counts[$status] ?? 0) + 1;

            if ($status === 'OK' && !$dryRun) {
                $updateQuery = FileIndexing::on('sqlsrv')->where('id', $row->id);
                if (!$force) {
                    $updateQuery->whereNull('latitude')->whereNull('longitude');
                }
                $written += $updateQuery->update([
                    'latitude'  => $result['lat'],
                    'longitude' => $result['lng'],
                ]);
            }
        }

        return [
            'processed' => $rows->count(),
            'written'   => $written,
            'remaining' => $this->remainingCount($force),
            'last_id'   => $lastId,
            'counts'    => $counts,
        ];
    }

    /**
     * Mirrors buildGeocodeAddress() in
     * resources/views/fileindexing/addons/create_indexing.blade.php:536-554
     * so bulk and manually-pinned coordinates stay consistent, plus resolving
     * legacy rows where district/lga were stored as bare reference-table IDs
     * (e.g. district="14") instead of names — geocoding those raw numbers
     * silently fell back to a generic Kano-state centroid.
     */
    private function buildGeocodeAddress(FileIndexing $row): ?string
    {
        $district = $this->resolveNamedPart($row->district, $this->districtNames);
        $lga = $this->resolveNamedPart($row->lga, $this->lgaNames);

        $realParts = [$row->plot_number, $row->street_name, $district, $lga];

        $clean = [];
        foreach ($realParts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            // Defensive: strip legacy "STREET: X" / "DISTRICT: X" / "LGA: X" / "STATE: X" labels.
            $part = preg_replace('/^(street|district|lga|state)\s*:\s*/i', '', $part);
            if (preg_match('/^select\s/i', $part)) {
                continue;
            }
            $clean[] = $part;
        }

        // Require at least one real location part — otherwise the address is just
        // "KANO, NIGERIA", which geocodes to a meaningless state-level centroid.
        if (count($clean) < 1) {
            return null;
        }

        $clean[] = 'KANO';
        $clean[] = 'NIGERIA';

        return implode(', ', $clean);
    }

    /**
     * If $value is a bare numeric reference-table ID, resolve it to its name;
     * unresolvable numeric IDs are dropped rather than sent to Google as-is.
     */
    private function resolveNamedPart(?string $value, array $namesById): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return $namesById[$value] ?? null;
        }
        return $value;
    }

    /**
     * @return array{status: string, lat?: float, lng?: float}
     */
    private function geocode(string $address, string $key): array
    {
        if (array_key_exists($address, $this->cache)) {
            $cached = $this->cache[$address];
            return $cached === null
                ? ['status' => 'ZERO_RESULTS']
                : ['status' => 'OK', 'lat' => $cached['lat'], 'lng' => $cached['lng']];
        }

        usleep(100_000);

        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'region'  => 'ng',
                'key'     => $key,
            ]);
        } catch (\Throwable $e) {
            $this->cache[$address] = null;
            return ['status' => 'ERROR'];
        }

        if (!$response->ok()) {
            $this->cache[$address] = null;
            return ['status' => 'ERROR'];
        }

        $body = $response->json();

        if (($body['status'] ?? null) !== 'OK' || empty($body['results'][0])) {
            $this->cache[$address] = null;
            return ['status' => $body['status'] ?? 'ERROR'];
        }

        $location = $body['results'][0]['geometry']['location'];
        $lat = round((float) $location['lat'], 7);
        $lng = round((float) $location['lng'], 7);

        $this->cache[$address] = ['lat' => $lat, 'lng' => $lng];

        return ['status' => 'OK', 'lat' => $lat, 'lng' => $lng];
    }
}
