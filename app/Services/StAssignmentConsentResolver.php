<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the ST Assignment "consent" that backs a sectional-titling file.
 *
 * A sectional file never carries a row in `consent_applications`. What stands in
 * for the consent is the approved primary / physical-planning MEMO on the mother
 * application — which is exactly what /deeds-applications lists as a row of type
 * "ST Assignment" (see DeedsApplicationController::index). Those rows are built
 * in memory and never persisted, so every lookup that only queries
 * `consent_applications` reports "no consent on this file" for an ST file.
 *
 * The first assignment of an ST unit is registered from /instrument_registration
 * (derived per subapplication). Any LATER assignment of the same unit is captured
 * on /instruments/create as an ordinary Deed of Assignment — and that is where the
 * consent lookup has to find this memo instead of coming up empty.
 *
 * Resolved on the fly on purpose: the memo stays the single source of truth, so
 * there is nothing to backfill and nothing to keep in sync.
 */
class StAssignmentConsentResolver
{
    /** The consent_type these synthetic rows carry — matches the deeds-applications label. */
    public const CONSENT_TYPE = 'ST Assignment';

    /**
     * "ST Assignment" (the consent / deeds-applications label) and
     * "ST Assignment (Transfer of Title)" (the instrument_type label used
     * throughout instrument registration) are the same thing under two names.
     * Everything that compares types should compare canonical forms.
     */
    public static function canonicalType(?string $type): string
    {
        $value = trim((string) $type);
        if ($value === '') {
            return '';
        }

        if (str_starts_with(strtoupper($value), 'ST ASSIGNMENT')) {
            return self::CONSENT_TYPE;
        }

        return $value;
    }

    public static function isStAssignmentType(?string $type): bool
    {
        return self::canonicalType($type) === self::CONSENT_TYPE;
    }

    /**
     * Every ST Assignment consent that covers the given file number.
     *
     * The file number may be either the mother application's file number or one of
     * the unit ST file numbers — both resolve to the same memo. When a unit matched,
     * the returned consent is narrowed to that unit's buyer and property.
     *
     * @return Collection<int, object> consent_applications-shaped rows, is_synthetic = true
     */
    public function forFileNumber(?string $fileNumber): Collection
    {
        $fileNo = trim((string) $fileNumber);
        if ($fileNo === '') {
            return collect();
        }

        [$motherIds, $unitByMother] = $this->resolveMotherApplications($fileNo);
        if (empty($motherIds)) {
            return collect();
        }

        $memos = $this->approvedMemos($motherIds);
        if ($memos->isEmpty()) {
            return collect();
        }

        return $memos->map(function ($memo) use ($unitByMother) {
            return $this->buildConsent($memo, $unitByMother[$memo->mother_id] ?? null);
        })->values();
    }

    /**
     * Mother application ids reachable from a file number, plus the matched unit
     * row (when the file number was a unit's own ST file number) keyed by mother id.
     *
     * @return array{0: array<int, mixed>, 1: array<mixed, object>}
     */
    private function resolveMotherApplications(string $fileNo): array
    {
        $upper = strtoupper($fileNo);
        $clean = preg_replace('/[^A-Z0-9]/', '', $upper);

        $matches = function ($query, array $columns) use ($fileNo, $upper, $clean) {
            $query->where(function ($q) use ($columns, $fileNo, $upper, $clean) {
                foreach ($columns as $column) {
                    $q->orWhere($column, $fileNo)
                        ->orWhere($column, $upper);
                    if ($clean !== '') {
                        $q->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE({$column}, ' ', ''), '-', ''), '/', '') = ?",
                            [$clean]
                        );
                    }
                }
            });
        };

        $motherIds = [];
        $unitByMother = [];

        // (a) the mother application's own file number
        $mothers = DB::connection('sqlsrv')->table('mother_applications')
            ->where(fn ($q) => $matches($q, ['fileno', 'np_fileno']))
            ->pluck('id');
        foreach ($mothers as $id) {
            $motherIds[$id] = $id;
        }

        // (b) a unit's ST file number — unit rows carry no mother_application_id,
        //     they hang off a subapplication or a buyer_list entry.
        $units = DB::connection('sqlsrv')->table('st_file_numbers')
            ->where(fn ($q) => $matches($q, ['fileno', 'mls_fileno']))
            ->get();

        $subIds   = $units->pluck('subapplication_id')->filter()->unique()->values();
        $buyerIds = $units->pluck('buyer_list_id')->filter()->unique()->values();

        $subs = $subIds->isEmpty() ? collect() : DB::connection('sqlsrv')->table('subapplications')
            ->whereIn('id', $subIds)->get()->keyBy('id');
        $buyers = $buyerIds->isEmpty() ? collect() : DB::connection('sqlsrv')->table('buyer_list')
            ->whereIn('id', $buyerIds)->get()->keyBy('id');

        foreach ($units as $unit) {
            $sub   = $unit->subapplication_id ? ($subs[$unit->subapplication_id] ?? null) : null;
            $buyer = $unit->buyer_list_id ? ($buyers[$unit->buyer_list_id] ?? null) : null;

            $motherId = $unit->mother_application_id
                ?: ($sub->main_application_id ?? null)
                ?: ($buyer->application_id ?? null);

            if (!$motherId) {
                continue;
            }

            $motherIds[$motherId] = $motherId;

            // A PRIMARY row is the mother file's own entry, not a unit — it carries
            // the mother's name, so treating it as a unit would make party 2 the
            // assignor. Only rows that hang off a subapplication or a buyer narrow
            // the consent to one unit.
            if (!$sub && !$buyer) {
                continue;
            }

            // First unit match wins; a file number identifies at most one unit.
            if (!isset($unitByMother[$motherId])) {
                $unitByMother[$motherId] = (object) [
                    'st_file' => $unit,
                    'subapp'  => $sub,
                    'buyer'   => $buyer,
                ];
            }
        }

        return [array_values($motherIds), $unitByMother];
    }

    /**
     * The memos that make an ST Assignment appear on /deeds-applications.
     * Kept identical to DeedsApplicationController::index so a file is registrable
     * exactly when its consent is visible there.
     */
    private function approvedMemos(array $motherIds): Collection
    {
        return DB::connection('sqlsrv')->table('memos')
            ->join('mother_applications', 'memos.application_id', '=', 'mother_applications.id')
            ->whereIn('mother_applications.id', $motherIds)
            ->where('mother_applications.application_status', 'Approved')
            ->where(function ($q) {
                $q->where('memos.memo_status', 'GENERATED')
                    ->orWhereNull('memos.memo_status')
                    ->orWhere('memos.memo_status', '');
            })
            ->whereIn(DB::raw('LOWER(memos.memo_type)'), ['primary', 'physical_planning', 'physical planning'])
            ->select(
                'memos.id as memo_id',
                'memos.memo_no',
                'memos.created_at',
                'memos.property_location as memo_property_location',
                'mother_applications.id as mother_id',
                'mother_applications.fileno as file_number',
                'mother_applications.np_fileno',
                'mother_applications.applicant_title',
                'mother_applications.first_name',
                'mother_applications.surname',
                'mother_applications.corporate_name',
                'mother_applications.address as applicant_address',
                'mother_applications.property_house_no',
                'mother_applications.property_plot_no',
                'mother_applications.property_street_name',
                'mother_applications.property_district',
                'mother_applications.property_lga',
                'mother_applications.property_state'
            )
            ->orderBy('memos.created_at', 'desc')
            ->get()
            ->unique('mother_id')
            ->values();
    }

    /**
     * Shape one memo as a consent_applications row so every consumer of a consent
     * (auto-fill, the consent picker, the registration gate) can treat it as one.
     */
    private function buildConsent(object $memo, ?object $unit): object
    {
        $party1 = strtoupper(trim(
            $memo->corporate_name ?: trim("{$memo->applicant_title} {$memo->first_name} {$memo->surname}")
        ));

        $stFile = $unit->st_file ?? null;
        $sub    = $unit->subapp ?? null;
        $buyer  = $unit->buyer ?? null;

        $party2 = $this->resolveParty2($memo, $sub, $buyer, $stFile);

        $house    = $this->firstFilled($stFile->property_house_no ?? null, $sub->address_house_no ?? null, $memo->property_house_no);
        $plot     = $this->firstFilled($stFile->property_plot_no ?? null, $memo->property_plot_no);
        $street   = $this->firstFilled($stFile->property_street_name ?? null, $sub->address_street_name ?? null, $memo->property_street_name);
        $district = $this->firstFilled($stFile->property_district ?? null, $sub->unit_district ?? null, $sub->address_district ?? null, $memo->property_district);
        $lga      = $this->firstFilled($stFile->property_lga ?? null, $sub->unit_lga ?? null, $sub->address_lga ?? null, $memo->property_lga);
        $state    = $this->firstFilled($stFile->property_state ?? null, $sub->unit_state ?? null, $sub->address_state ?? null, $memo->property_state);

        $description = $this->firstFilled(
            $stFile->property_address ?? null,
            $sub->property_location ?? null,
            $memo->memo_property_location,
            $this->composeDescription($house, $plot, $street, $district, $lga, $state)
        );

        return (object) [
            // Non-numeric on purpose: there is no consent_applications row to link
            // to, and InstrumentCaptureService::resolveConsentApplicationId stores
            // NULL for anything that is not a positive integer.
            'id'                      => 'memo_' . $memo->memo_id,
            'memo_id'                 => $memo->memo_id,
            'application_tracking_no' => $memo->memo_no,
            'consent_type'            => self::CONSENT_TYPE,
            'file_number'             => $memo->file_number,
            'unit_file_number'        => $stFile->fileno ?? null,
            'np_fileno'               => $memo->np_fileno,
            'applicant_name'          => $party1,
            'applicant_address'       => $memo->applicant_address,
            'party_name'              => $party2,
            'party_address'           => $sub->address ?? null,
            'property_description'    => $description ? strtoupper($description) : null,
            'property_house_no'       => $house,
            'plot_number'             => $plot,
            'property_street'         => $street,
            'property_district'       => $district,
            'property_lga'            => $lga,
            'property_state'          => $state,
            'application_date'        => $memo->created_at,
            'created_at'              => $memo->created_at,
            'status'                  => 'Approved',
            'print_count'             => 0,
            'additional_properties'   => [],

            // Markers for consumers.
            'is_synthetic'            => true,
            'source'                  => 'memo',
            // An ST Assignment memo backs the unit's first transfer and every later
            // one, so it is never "spent" the way a one-shot consent letter is.
            'is_reusable'             => true,
        ];
    }

    private function resolveParty2(object $memo, ?object $sub, ?object $buyer, ?object $stFile): string
    {
        if ($buyer) {
            return strtoupper(trim("{$buyer->buyer_title} {$buyer->buyer_name}"));
        }

        if ($sub) {
            return strtoupper(trim(
                $sub->corporate_name ?: trim("{$sub->applicant_title} {$sub->first_name} {$sub->surname}")
            ));
        }

        if ($stFile) {
            $name = $stFile->corporate_name ?: trim("{$stFile->applicant_title} {$stFile->first_name} {$stFile->surname}");
            if (trim((string) $name) !== '') {
                return strtoupper(trim($name));
            }
        }

        // Mother file number given, no single unit implied — summarise the units the
        // same way the deeds-applications row does.
        return $this->summariseUnits($memo->mother_id);
    }

    private function summariseUnits($motherId): string
    {
        $names = DB::connection('sqlsrv')->table('buyer_list')
            ->where('application_id', $motherId)
            ->get(['buyer_title', 'buyer_name'])
            ->map(fn ($b) => strtoupper(trim("{$b->buyer_title} {$b->buyer_name}")))
            ->filter(fn ($n) => $n !== '')
            ->values();

        if ($names->isEmpty()) {
            $names = DB::connection('sqlsrv')->table('subapplications')
                ->where('main_application_id', $motherId)
                ->get(['applicant_title', 'first_name', 'surname', 'corporate_name'])
                ->map(fn ($s) => strtoupper(trim(
                    $s->corporate_name ?: trim("{$s->applicant_title} {$s->first_name} {$s->surname}")
                )))
                ->filter(fn ($n) => $n !== '')
                ->values();
        }

        if ($names->isEmpty()) {
            return 'N/A';
        }

        $first = $names->first();

        return $names->count() > 1
            ? "{$first} & (" . ($names->count() - 1) . ') Others'
            : $first;
    }

    private function firstFilled(...$values): ?string
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function composeDescription(...$parts): ?string
    {
        $text = collect($parts)
            ->map(fn ($p) => trim((string) $p))
            ->filter(fn ($p) => $p !== '')
            ->implode(', ');

        return $text !== '' ? $text : null;
    }
}
