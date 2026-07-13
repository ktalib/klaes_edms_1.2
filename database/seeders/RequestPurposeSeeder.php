<?php

namespace Database\Seeders;

use App\Models\RequestPurpose;
use Illuminate\Database\Seeder;

/**
 * Syncs request_purposes against the canonical list in docs/data/Purpose.csv (one name
 * per line, header row "name"). Canonical names are created/reactivated; any existing
 * row not in the list is deactivated rather than deleted, since file_tracker rows may
 * still reference its id. Re-run safely: php artisan db:seed --class=RequestPurposeSeeder
 */
class RequestPurposeSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('docs/data/Purpose.csv');
        if (!file_exists($path)) {
            $this->command?->error("Purpose.csv not found at {$path}");
            return;
        }

        $lines = preg_split('/\r\n|\r|\n/', file_get_contents($path));
        $skip = ['', 'name', 's/n'];
        $names = [];

        foreach ($lines as $line) {
            $name = trim($line);
            $key = strtolower($name);

            if ($name === '' || in_array($key, $skip, true) || isset($names[$key])) {
                continue;
            }
            $names[$key] = $name;
        }

        foreach ($names as $name) {
            RequestPurpose::updateOrCreate(
                ['name' => $name],
                ['turnaround_days' => 5, 'is_active' => true]
            );
        }

        $deactivated = RequestPurpose::whereNotIn('name', array_values($names))
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $this->command?->info('Synced ' . count($names) . ' request purposes, deactivated ' . $deactivated . ' stale entries.');
    }
}
