<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sample data for the Create File Index form — a testing aid, not a feature.
 *
 * Filling that form by hand is forty-odd fields, and doing it for every test run
 * is most of the cost of testing indexing at all. This hands back one ready-made
 * form payload instead.
 *
 * WHAT IS REAL AND WHAT IS INVENTED — the distinction matters, because the danger
 * is not a broken page, it is an operator indexing a real file against made-up
 * particulars:
 *
 *   REAL      the file number and tracking ID. They come from an actual `grouping`
 *             row that is NOT yet in file_indexings, so the save exercises the
 *             genuine grouping -> indexing path rather than a synthetic one. A
 *             fabricated file number would test nothing that matters.
 *
 *   INVENTED  every human particular — holder names, company, address, NIN, TIN,
 *             phone, plot. Local (Kano / Northern Nigerian) names, so the result
 *             reads like a plausible record rather than "Test Test 1".
 *
 * Gated twice by config('fileindexing.demo_fill') — an explicit env flag AND a
 * non-production APP_ENV. See that config block for why both.
 */
class DemoIndexingDataService
{
    /**
     * Surnames and given names in common use in Kano and the North-West.
     * Combined at random, so the pool is far larger than the list length.
     */
    private const GIVEN_NAMES = [
        'Aminu', 'Sani', 'Yusuf', 'Ibrahim', 'Abubakar', 'Musa', 'Bashir', 'Nasiru',
        'Kabiru', 'Salisu', 'Auwalu', 'Lawal', 'Shehu', 'Umar', 'Garba', 'Idris',
        'Mustapha', 'Haruna', 'Tijjani', 'Sulaiman', 'Zubairu', 'Halima', 'Aisha',
        'Zainab', 'Fatima', 'Hauwa', 'Maryam', 'Rukayya', 'Hadiza', 'Amina',
        'Bilkisu', 'Safiya', 'Khadija', 'Jamila', 'Rabi',
    ];

    private const FAMILY_NAMES = [
        'Abdullahi', 'Muhammad', 'Danjuma', 'Gwarzo', 'Rano', 'Dala', 'Kano',
        'Bichi', 'Tsanyawa', 'Gezawa', 'Wudil', 'Dawakin Tofa', 'Karaye',
        'Maikano', 'Inuwa', 'Sarki', 'Galadima', 'Madaki', 'Wali', 'Ciroma',
        'Dantata', 'Rabiu', 'Bello', 'Yakasai', 'Kurmi', 'Gabari', 'Jibril',
    ];

    /** Industrial land use means most holders are companies, not individuals. */
    private const COMPANY_STEMS = [
        'Arewa', 'Kano North', 'Dala', 'Sahel', 'Tiga', 'Challawa', 'Bompai',
        'Sharada', 'Gwarzo', 'Zaria Road', 'Hadejia', 'Rimin Gado', 'Panshekara',
    ];

    private const COMPANY_TAILS = [
        'Industries Limited', 'Manufacturing Company Limited', 'Steel Works Limited',
        'Textile Mills Limited', 'Agro Allied Limited', 'Plastics Limited',
        'Foods & Beverages Limited', 'Tannery Limited', 'Flour Mills Limited',
        'Chemicals Limited', 'Packaging Industries Limited',
    ];

    /** Real Kano industrial areas, so the address reads correctly. */
    private const STREETS = [
        'Bompai Road', 'Sharada Industrial Road', 'Challawa Industrial Road',
        'Zaria Road', 'Hadejia Road', 'Maiduguri Road', 'Katsina Road',
        'Gwarzo Road', 'Airport Road', 'Club Road', 'Independence Road',
    ];

    private const DISTRICTS = [
        'Bompai', 'Sharada', 'Challawa', 'Panshekara', 'Tokarawa',
        'Gunduwawa', 'Dakata', 'Zango', 'Kwakwaci',
    ];

    private const LGAS = [
        'Nassarawa', 'Kumbotso', 'Fagge', 'Dala', 'Gwale', 'Tarauni', 'Ungogo',
    ];

    /**
     * Whether the demo fill is available at all.
     *
     * Both locks are checked here, so no caller can accidentally satisfy one and
     * forget the other.
     */
    public function enabled(): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        return (bool) config('fileindexing.demo_fill.enabled', false);
    }

    /**
     * One ready-to-submit form payload, or null when no unindexed file is left.
     *
     * @param  array<int,string> $exclude file numbers already handed out this session,
     *                                    so clicking the button twice does not offer
     *                                    the same file again.
     * @return array<string,mixed>|null
     */
    public function sample(array $exclude = []): ?array
    {
        if (!$this->enabled()) {
            return null;
        }

        try {
            $grouping = $this->pickUnindexedGrouping($exclude);

            if (!$grouping) {
                return null;
            }

            return [
                // Real — straight off the grouping row.
                'source' => [
                    'grouping_id' => $grouping->id ?? null,
                    'file_number' => $grouping->awaiting_fileno,
                    'tracking_id' => $grouping->tracking_id,
                    'registry' => $grouping->registry,
                    'land_use' => $grouping->landuse,
                    'year' => $grouping->year ?? null,
                ],
                // Invented — everything a human would have typed.
                'form' => $this->fabricateDetails($grouping),
            ];
        } catch (\Throwable $e) {
            Log::warning('DemoIndexingDataService::sample - failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * A grouping row whose file has never been indexed.
     *
     * The NOT EXISTS is written WITHOUT UPPER()/LTRIM() wrappers on purpose: this
     * database's collation is already case-insensitive, and wrapping the columns
     * makes the predicate non-sargable — the same scan-instead-of-seek that made
     * the file-log listing time out. Unwrapped this runs in well under a second
     * across ~10k candidates.
     */
    private function pickUnindexedGrouping(array $exclude)
    {
        $registry = (string) config('fileindexing.demo_fill.registry', 'Lands Registry');
        $landUse = (string) config('fileindexing.demo_fill.land_use', 'INDUSTRIAL');

        $bindings = [$registry, $landUse];
        $excludeSql = '';

        // Cap the exclusion list: a session that has burned through hundreds of
        // samples should not build an unbounded IN clause.
        $exclude = array_slice(array_values(array_filter($exclude)), 0, 50);
        if (!empty($exclude)) {
            $excludeSql = ' AND g.awaiting_fileno NOT IN (' . implode(',', array_fill(0, count($exclude), '?')) . ')';
            $bindings = array_merge($bindings, $exclude);
        }

        // ORDER BY NEWID() = a random row, so repeated test runs do not all land
        // on the same file and collide with each other.
        $sql = "SELECT TOP 1 g.id, g.awaiting_fileno, g.tracking_id, g.registry,
                       g.landuse, g.[year] AS [year]
                  FROM grouping g
                 WHERE g.registry = ?
                   AND g.landuse = ?
                   AND g.deleted_at IS NULL
                   AND ISNULL(g.awaiting_fileno, '') <> ''
                   AND ISNULL(g.tracking_id, '') <> ''
                   AND NOT EXISTS (
                         SELECT 1 FROM file_indexings fi
                          WHERE fi.file_number = g.awaiting_fileno
                       )
                       {$excludeSql}
                 ORDER BY NEWID()";

        $rows = DB::connection('sqlsrv')->select($sql, $bindings);

        return $rows[0] ?? null;
    }

    /**
     * The invented half: everything the operator would otherwise type.
     *
     * @return array<string,mixed>
     */
    private function fabricateDetails($grouping): array
    {
        $company = $this->pick(self::COMPANY_STEMS) . ' ' . $this->pick(self::COMPANY_TAILS);
        $contact = $this->pick(self::GIVEN_NAMES) . ' ' . $this->pick(self::FAMILY_NAMES);
        $previous = $this->pick(self::GIVEN_NAMES) . ' ' . $this->pick(self::FAMILY_NAMES);

        $plotNumber = random_int(1, 400);
        $street = $this->pick(self::STREETS);
        $district = $this->pick(self::DISTRICTS);
        $lga = $this->pick(self::LGAS);

        return [
            'file_title' => $company,
            'file_type' => 'Corporate',
            'general_registry' => 'Land Registry',
            'indexing_type' => 'Regular',
            'land_use_type' => 'INDUSTRIAL',

            // Property
            'plot_number' => (string) $plotNumber,
            'plot_size' => (string) random_int(1, 25) . '.' . random_int(0, 9),
            'street_name' => $street,
            'district' => $district,
            'lga' => $lga,
            'location' => 'PLOT ' . $plotNumber . ', ' . strtoupper($street) . ', '
                . strtoupper($district) . ', ' . strtoupper($lga) . ', KANO STATE',
            'tp_no' => 'TP/KN/' . random_int(1990, 2024) . '/' . random_int(100, 999),
            'lpkn_no' => 'LPKN/' . random_int(1000, 9999),
            'property_description' => 'Industrial warehouse and ancillary structures',

            // Parties. Industrial land is usually held by a company, so the entity
            // is the holder and the individual is the contact person.
            'entity_type' => 'Corporate',
            'entity_name' => $company,
            'customer_type' => 'Corporate',
            'customer_name' => $company,
            // Root of Title is required on the form, so the demo fill has to supply
            // one or a demo record cannot be saved.
            'root_of_title' => 'Customary Right of Occupancy - ' . strtoupper($previous),
            'current_holder' => $company,
            'original_holder' => $previous,
            'applicant_name' => $contact,
            'gender' => 'Male',

            // Identifiers. Digit counts match the form's own validation
            // (NIN is 13 digits; see the input handler in create_indexing.blade.php)
            // so demo data cannot be rejected for a reason a real record would not be.
            'nin' => (string) random_int(1000000000000, 9999999999999),
            'tin' => (string) random_int(10000000, 99999999),
            'rc_no' => 'RC' . random_int(100000, 999999),
            'phone' => '080' . random_int(10000000, 99999999),
            'country_code' => '+234',
            'email' => strtolower(str_replace(' ', '.', $contact)) . '@example.test',
            'residence_address' => 'No. ' . random_int(1, 90) . ', ' . $this->pick(self::STREETS)
                . ', ' . $this->pick(self::DISTRICTS) . ', KANO',
            'dob' => sprintf('%04d-%02d-%02d', random_int(1960, 1995), random_int(1, 12), random_int(1, 28)),
        ];
    }

    private function pick(array $pool): string
    {
        return $pool[random_int(0, count($pool) - 1)];
    }
}
