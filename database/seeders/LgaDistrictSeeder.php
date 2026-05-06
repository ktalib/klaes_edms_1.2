<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LgaDistrictSeeder extends Seeder
{
    public function run(): void
    {
        $connection = DB::connection('sqlsrv');
        $now = Carbon::now();

        $lgas = [
            'Ajingi', 'Albasu', 'Bagwai', 'Bebeji', 'Bichi', 'Bunkure', 'Dala', 'Dambatta',
            'Dawakin Kudu', 'Dawakin Tofa', 'Doguwa', 'Fagge', 'Gabasawa', 'Garko', 'Garun Mallam',
            'Gaya', 'Gezawa', 'Gwale', 'Gwarzo', 'Kabo', 'Kano Municipal', 'Karaye', 'Kibiya',
            'Kiru', 'Kumbotso', 'Kunchi', 'Kura', 'Madobi', 'Makoda', 'Minjibir', 'Nasarawa',
            'Rano', 'Rimin Gado', 'Rogo', 'Shanono', 'Sumaila', 'Takai', 'Tarauni', 'Tofa',
            'Tsanyawa', 'Tudun Wada', 'Ungogo', 'Warawa', 'Wudil'
        ];

        $lgaRecords = collect($lgas)->map(function (string $name) use ($now) {
            $formatted = $this->formatName($name);
            $slug = Str::slug($formatted);

            return [
                'name' => $formatted,
                'code' => Str::upper(str_replace('-', '_', $slug)),
                'slug' => $slug,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });

        $connection->table('lgas')->upsert(
            $lgaRecords->all(),
            ['name'],
            ['code', 'slug', 'is_active', 'updated_at']
        );

        // Create independent district records (no LGA relationship)
        $districtNames = [
            'Ajingi', 'Albasu', 'Bagwai', 'Bebeji', 'Bichi', 'Bunkure', 'City', 'City District', 
            'Dala', 'Dambatta', 'Dawakin Kudu', 'Dawakin Tofa', 'Doguwa', 'Dorayi Karama', 
            'Fagge', 'Gabasawa', 'Garko', 'Garun Mallam', 'Gaya', 'Gezawa', 'Gwale', 'Gwamaja',
            'Gwarzo', 'Hausawa', 'Inubawa', 'Kabo', 'Kano City', 'Kano Municipal', 'Karaye', 
            'Kibiya', 'Kiru', 'Kumbotso', 'Kunchi', 'Kura', 'Madobi', 'Makoda', 'Minjibir', 
            'Municipal', 'Nasarawa', 'Rano', 'Rimin Gado', 'Rimin Zakara', 'Rogo', 'Shanono',
            'Sumaila', 'Takai', 'Tarauni', 'Tofa', 'Tsanyawa', 'Tudun Wada', 'Ungogo', 'Waje',
            'Warawa', 'Wudil', 'Zawachiki'
        ];

        $districtRecords = collect($districtNames)->map(function (string $name) use ($now) {
            $formatted = $this->formatName($name);
            $slug = Str::slug($formatted) ?: strtolower($this->normalizeKey($formatted));

            return [
                'name' => $formatted,
                'slug' => $slug,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->unique('name')->values();

        $connection->table('districts')->upsert(
            $districtRecords->all(),
            ['name'],
            ['slug', 'is_active', 'updated_at']
        );
    }

    protected function normalizeKey(string $value): string
    {
        $value = Str::of($value)->upper();
        $value = str_replace([' ', '-', '/', '\\', '.', "'"], '', $value);
        return (string) $value;
    }

    protected function formatName(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value));
        $value = str_replace(['–', '—'], '-', $value);
        return Str::of($value)->lower()->title();
    }
}
