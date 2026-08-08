<?php

namespace App\Http\Controllers;

use App\Models\ConsentApplication;
use App\Models\PrintLog;
use App\Models\FileNumber;
use App\Models\StreetName;
use App\Models\LandUseType;
use App\Models\Purpose;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ConsentApplicationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        $applications = ConsentApplication::orderBy('created_at', 'desc')->get();
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = DB::connection('sqlsrv')->table('StatLGAs')->join('States', 'StatLGAs.StateID', '=', 'States.StateID')->where('States.StateName', 'Kano')->orderBy('LGAName')->get();
        $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();
        $landUseTypes = LandUseType::query()->where('is_active', 1)->orderBy('name')->get(['id', 'name']);
        $purposes = Purpose::query()->orderBy('name')->get(['id', 'landuseid', 'name']);

        return view('consent_applications.index', compact('applications', 'states', 'lgas', 'districts', 'streetNames', 'landUseTypes', 'purposes'));
    }

    /**
     * Next tracking number for the current year, e.g. CONS-2026-0004.
     *
     * A filtered unique index backs the column, so a racing duplicate fails
     * loudly rather than silently reusing a number — see the retry in store().
     *
     * @return string
     */
    private function generateTrackingNo()
    {
        $prefix = 'CONS-' . date('Y') . '-';
        $offset = strlen($prefix) + 1; // SQL SUBSTRING is 1-based

        $lastSeq = (int) ConsentApplication::where('application_tracking_no', 'like', $prefix . '%')
            ->selectRaw("MAX(CAST(SUBSTRING(application_tracking_no, {$offset}, 10) AS INT)) as seq")
            ->value('seq');

        return $prefix . str_pad((string) ($lastSeq + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Collect the extra file numbers attached to an application, each with the
     * property description captured for it.
     *
     * Rows without a file number are dropped, as are duplicates and any row
     * repeating the primary file number, so a file is never stored twice.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    private function additionalProperties(Request $request)
    {
        $primary = strtoupper(trim((string) $request->file_number));
        $seen = [];
        $properties = [];

        foreach ((array) $request->input('additional_file_number', []) as $index => $fileNumber) {
            $fileNumber = trim((string) $fileNumber);
            $key = strtoupper($fileNumber);

            if ($fileNumber === '' || $key === $primary || in_array($key, $seen, true)) {
                continue;
            }
            $seen[] = $key;

            $field = fn($name) => trim((string) ($request->input($name, [])[$index] ?? ''));

            $district = $field('additional_property_district') === 'Other'
                ? $field('additional_property_district_other')
                : $field('additional_property_district');

            $properties[] = [
                'file_number' => $fileNumber,
                'applicant_name' => $field('additional_property_applicant'),
                'house_no' => $field('additional_property_house_no'),
                'street' => $field('additional_property_street'),
                'district' => $district,
                'lga' => $field('additional_property_lga'),
                'state' => $field('additional_property_state'),
                'description' => $field('additional_property_description'),
            ];
        }

        return $properties;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $applicationDateRule = $request->filled('application_submitted_date') ? 'nullable|date' : 'required|date';
        $request->validate([
            'file_number' => 'required|string',
            'consent_type' => 'required|in:Assignment,Gift,Mortgage',
            'applicant_name' => 'required|string',
            'applicant_address' => 'required|string',
            'party_name' => 'required|string',
            'party_address' => 'required|string',
            'property_description' => 'required|string',
            'application_date' => $applicationDateRule,
            'application_submitted_date' => 'nullable|date',
            'application_type' => 'nullable|string|max:120',
            'consideration' => 'nullable|string',
            'consideration_words' => 'nullable|string',
            'right_of_occupancy_number' => 'nullable|string|max:120',
            'right_of_occupancy_landuse' => 'nullable|string|max:120',
            'purpose_of_right_of_occupancy' => 'nullable|string|max:150',
            'original_holder_name' => 'nullable|string|max:150',
            'correspondence_address' => 'nullable|string',
            'postal_address_gsm' => 'nullable|string|max:150',
            'nationality_state_of_origin' => 'nullable|string|max:150',
                'state_of_origin' => 'nullable|string|max:120',
                'nationality' => 'nullable|string|max:120',
            'stage_of_development' => 'nullable|string|max:150',
            'location_of_right_of_occupancy' => 'nullable|string|max:150',
            'date_of_grant' => 'nullable|date',
            'date_of_grant_purpose' => 'nullable|string|max:150',
            'special_mortgage_terms' => 'nullable|string',
            'additional_file_number' => 'nullable|array',
            'additional_file_number.*' => 'nullable|string|max:120',
            'additional_property_applicant' => 'nullable|array',
            'additional_property_house_no' => 'nullable|array',
            'additional_property_street' => 'nullable|array',
            'additional_property_district' => 'nullable|array',
            'additional_property_district_other' => 'nullable|array',
            'additional_property_lga' => 'nullable|array',
            'additional_property_state' => 'nullable|array',
            'additional_property_description' => 'nullable|array',
            'additional_party_name' => 'nullable|array',
            'additional_party_house_no' => 'nullable|array',
            'additional_party_street' => 'nullable|array',
            'additional_party_district' => 'nullable|array',
            'additional_party_lga' => 'nullable|array',
            'additional_party_state' => 'nullable|array',
            'additional_applicant_name' => 'nullable|array',
            'additional_applicant_house_no' => 'nullable|array',
            'additional_applicant_street' => 'nullable|array',
            'additional_applicant_district' => 'nullable|array',
            'additional_applicant_lga' => 'nullable|array',
            'additional_applicant_state' => 'nullable|array',
        ]);

        // A file is assigned again and again over its life, so one consent per file
        // number is wrong — it blocked every subsequent assignment. What must not
        // repeat is the same transaction: the same file to the same assignee.
        $assignee = trim((string) $request->party_name);

        if ($conflict = $this->assigneeConflictMessage($request)) {
            return response()->json(['success' => false, 'message' => $conflict], 422);
        }

        $existing = ConsentApplication::where('file_number', $request->file_number)
            ->whereRaw('LOWER(LTRIM(RTRIM(party_name))) = ?', [mb_strtolower($assignee)])
            ->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'A consent application for file number ' . $request->file_number
                    . ' in favour of ' . $assignee . ' already exists (ref '
                    . ($existing->application_tracking_no ?: '#' . $existing->id) . ').'
            ], 422);
        }

        $data = $request->all();
        $data['date_of_grant_purpose'] = collect([
            $request->date_of_grant,
            $request->purpose_of_right_of_occupancy,
        ])->filter()->implode(' - ');

        // Handle additional parties
        $additionalParties = [];
        if ($request->has('additional_party_name') && is_array($request->additional_party_name)) {
            foreach ($request->additional_party_name as $index => $name) {
                if (!empty($name)) {
                    $houseNo = $request->additional_party_house_no[$index] ?? '';
                    $street = $request->additional_party_street[$index] ?? '';
                    $district = $request->additional_party_district[$index] ?? '';
                    $lga = $request->additional_party_lga[$index] ?? '';
                    $state = $request->additional_party_state[$index] ?? '';

                    // Build full address string for print template compatibility
                    $parts = [];
                    if ($houseNo) $parts[] = "House No " . $houseNo;
                    if ($street) $parts[] = $street;
                    if ($district) $parts[] = $district;
                    if ($lga) $parts[] = $lga . " LGA";
                    if ($state) $parts[] = $state . " State";
                    $builtAddress = implode(", ", $parts) . ".";

                    $additionalParties[] = [
                        'name' => $name,
                        'house_no' => $houseNo,
                        'street' => $street,
                        'district' => $district,
                        'lga' => $lga,
                        'state' => $state,
                        'address' => $builtAddress
                    ];
                }
            }
        }
        $data['additional_parties'] = $additionalParties;

        // Handle additional applicants
        $additionalApplicants = [];
        if ($request->has('additional_applicant_name') && is_array($request->additional_applicant_name)) {
            foreach ($request->additional_applicant_name as $index => $name) {
                if (!empty($name)) {
                    $houseNo = $request->additional_applicant_house_no[$index] ?? '';
                    $street = $request->additional_applicant_street[$index] ?? '';
                    $district = $request->additional_applicant_district[$index] ?? '';
                    $lga = $request->additional_applicant_lga[$index] ?? '';
                    $state = $request->additional_applicant_state[$index] ?? '';

                    // Build full address string for print template compatibility
                    $parts = [];
                    if ($houseNo) $parts[] = "House No " . $houseNo;
                    if ($street) $parts[] = $street;
                    if ($district) $parts[] = $district;
                    if ($lga) $parts[] = $lga . " LGA";
                    if ($state) $parts[] = $state . " State";
                    $builtAddress = implode(", ", $parts) . ".";

                    $additionalApplicants[] = [
                        'name' => $name,
                        'house_no' => $houseNo,
                        'street' => $street,
                        'district' => $district,
                        'lga' => $lga,
                        'state' => $state,
                        'address' => $builtAddress
                    ];
                }
            }
        }
        $data['additional_applicants'] = $additionalApplicants;

        $data['additional_properties'] = $this->additionalProperties($request);

        $data['c_of_o_no'] = $request->file_number;
        $data['user_id'] = Auth::id();
        $data['created_by'] = Auth::user()->first_name . ' ' . Auth::user()->last_name;

        // Two users saving at once can generate the same number; the unique index
        // rejects the loser, so re-read the sequence and try again.
        $application = null;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $data['application_tracking_no'] = $this->generateTrackingNo();
            try {
                $application = ConsentApplication::create($data);
                break;
            } catch (QueryException $e) {
                if ($attempt === 4) {
                    throw $e;
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Application generated successfully.',
            'id' => $application->id,
            'application_tracking_no' => $application->application_tracking_no
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $application = ConsentApplication::findOrFail($id);

        if ($application->print_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit an application that has already been printed.'
            ], 403);
        }

        $applicationDateRule = $request->filled('application_submitted_date') ? 'nullable|date' : 'required|date';
        $request->validate([
            'file_number' => 'required|string',
            'consent_type' => 'required|in:Assignment,Gift,Mortgage',
            'applicant_name' => 'required|string',
            'applicant_address' => 'required|string',
            'party_name' => 'required|string',
            'party_address' => 'required|string',
            'property_description' => 'required|string',
            'application_date' => $applicationDateRule,
            'application_submitted_date' => 'nullable|date',
            'application_type' => 'nullable|string|max:120',
            'consideration' => 'nullable|string',
            'consideration_words' => 'nullable|string',
            'right_of_occupancy_number' => 'nullable|string|max:120',
            'right_of_occupancy_landuse' => 'nullable|string|max:120',
            'purpose_of_right_of_occupancy' => 'nullable|string|max:150',
            'original_holder_name' => 'nullable|string|max:150',
            'correspondence_address' => 'nullable|string',
            'postal_address_gsm' => 'nullable|string|max:150',
            'nationality_state_of_origin' => 'nullable|string|max:150',
                'state_of_origin' => 'nullable|string|max:120',
                'nationality' => 'nullable|string|max:120',
            'stage_of_development' => 'nullable|string|max:150',
            'location_of_right_of_occupancy' => 'nullable|string|max:150',
            'date_of_grant' => 'nullable|date',
            'date_of_grant_purpose' => 'nullable|string|max:150',
            'special_mortgage_terms' => 'nullable|string',
            'additional_file_number' => 'nullable|array',
            'additional_file_number.*' => 'nullable|string|max:120',
            'additional_property_applicant' => 'nullable|array',
            'additional_property_house_no' => 'nullable|array',
            'additional_property_street' => 'nullable|array',
            'additional_property_district' => 'nullable|array',
            'additional_property_district_other' => 'nullable|array',
            'additional_property_lga' => 'nullable|array',
            'additional_property_state' => 'nullable|array',
            'additional_property_description' => 'nullable|array',
            'additional_party_name' => 'nullable|array',
            'additional_party_house_no' => 'nullable|array',
            'additional_party_street' => 'nullable|array',
            'additional_party_district' => 'nullable|array',
            'additional_party_lga' => 'nullable|array',
            'additional_party_state' => 'nullable|array',
            'additional_applicant_name' => 'nullable|array',
            'additional_applicant_house_no' => 'nullable|array',
            'additional_applicant_street' => 'nullable|array',
            'additional_applicant_district' => 'nullable|array',
            'additional_applicant_lga' => 'nullable|array',
            'additional_applicant_state' => 'nullable|array',
        ]);

        // Same party rules as store(): an edit must not be able to introduce what a
        // create forbids.
        if ($conflict = $this->assigneeConflictMessage($request)) {
            return response()->json(['success' => false, 'message' => $conflict], 422);
        }

        $data = $request->all();
        $data['date_of_grant_purpose'] = collect([
            $request->date_of_grant,
            $request->purpose_of_right_of_occupancy,
        ])->filter()->implode(' - ');

        // Handle additional parties
        $additionalParties = [];
        if ($request->has('additional_party_name') && is_array($request->additional_party_name)) {
            foreach ($request->additional_party_name as $index => $name) {
                if (!empty($name)) {
                    $houseNo = $request->additional_party_house_no[$index] ?? '';
                    $street = $request->additional_party_street[$index] ?? '';
                    $district = $request->additional_party_district[$index] ?? '';
                    $lga = $request->additional_party_lga[$index] ?? '';
                    $state = $request->additional_party_state[$index] ?? '';

                    // Build full address string for print template compatibility
                    $parts = [];
                    if ($houseNo) $parts[] = "House No " . $houseNo;
                    if ($street) $parts[] = $street;
                    if ($district) $parts[] = $district;
                    if ($lga) $parts[] = $lga . " LGA";
                    if ($state) $parts[] = $state . " State";
                    $builtAddress = implode(", ", $parts) . ".";

                    $additionalParties[] = [
                        'name' => $name,
                        'house_no' => $houseNo,
                        'street' => $street,
                        'district' => $district,
                        'lga' => $lga,
                        'state' => $state,
                        'address' => $builtAddress
                    ];
                }
            }
        }
        $data['additional_parties'] = $additionalParties;

        // Handle additional applicants
        $additionalApplicants = [];
        if ($request->has('additional_applicant_name') && is_array($request->additional_applicant_name)) {
            foreach ($request->additional_applicant_name as $index => $name) {
                if (!empty($name)) {
                    $houseNo = $request->additional_applicant_house_no[$index] ?? '';
                    $street = $request->additional_applicant_street[$index] ?? '';
                    $district = $request->additional_applicant_district[$index] ?? '';
                    $lga = $request->additional_applicant_lga[$index] ?? '';
                    $state = $request->additional_applicant_state[$index] ?? '';

                    // Build full address string for print template compatibility
                    $parts = [];
                    if ($houseNo) $parts[] = "House No " . $houseNo;
                    if ($street) $parts[] = $street;
                    if ($district) $parts[] = $district;
                    if ($lga) $parts[] = $lga . " LGA";
                    if ($state) $parts[] = $state . " State";
                    $builtAddress = implode(", ", $parts) . ".";

                    $additionalApplicants[] = [
                        'name' => $name,
                        'house_no' => $houseNo,
                        'street' => $street,
                        'district' => $district,
                        'lga' => $lga,
                        'state' => $state,
                        'address' => $builtAddress
                    ];
                }
            }
        }
        $data['additional_applicants'] = $additionalApplicants;

        $data['additional_properties'] = $this->additionalProperties($request);

        $data['c_of_o_no'] = $request->file_number;
        
        // Preserve application_type if not provided or empty (don't clear existing value)
        if (empty($data['application_type']) && $application->application_type) {
            $data['application_type'] = $application->application_type;
        }

        // The tracking number is issued once at creation and never reassigned.
        unset($data['application_tracking_no']);

        $application->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Application updated successfully.',
            'id' => $application->id
        ]);
    }

    /**
     * The assignee must be a different person from both the applicant and the party
     * who currently holds the title:
     *  - assignee === applicant  → a transfer to oneself, a no-op.
     *  - assignee === original holder → the file title already reads that name, so
     *    this transaction has already been registered.
     *
     * Mirrored client-side by checkAssigneeNameConflict() in consent_applications.js,
     * which flags it live as the name is typed. This is the authoritative copy.
     *
     * @return string|null Null when there is no conflict.
     */
    private function assigneeConflictMessage(Request $request): ?string
    {
        $assignee  = trim((string) $request->party_name);
        $applicant = trim((string) $request->applicant_name);
        $holder    = trim((string) $request->original_holder_name);

        if ($assignee === '') {
            return null;
        }

        if ($applicant !== '' && strcasecmp($applicant, $assignee) === 0) {
            return 'The Assignee cannot be the same person as the Applicant (' . $assignee
                . '). A property cannot be transferred to its own holder.';
        }

        if ($holder !== '' && strcasecmp($holder, $assignee) === 0) {
            return $assignee . ' is already the registered holder of file ' . $request->file_number
                . '. A consent cannot assign a property to the party who already holds it.';
        }

        return null;
    }

    public function lookupByFileNumber(Request $request)
    {
        $fileNumber = trim((string) $request->query('file_number'));

        if ($fileNumber === '') {
            return response()->json([
                'success' => false,
                'message' => 'File number is required.'
            ], 400);
        }

        $application = ConsentApplication::where('file_number', $fileNumber)
            ->where('application_type', 'application_for_censent')
            ->orderByDesc('created_at')
            ->first();

        if (!$application) {
            return response()->json([
                'success' => true,
                'data' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $application
        ]);
    }

    /**
     * API: Check whether a consent application exists for a file number and consent type,
     * and whether the consent letter has been printed.
     * Used by the instrument capture flow to gate Assignment/Gift registration.
     */
    public function checkForInstrument(Request $request)
    {
        $fileNo      = trim((string) $request->query('file_number', ''));
        $consentType = trim((string) $request->query('consent_type', ''));

        if ($fileNo === '' || $consentType === '') {
            return response()->json(['has_consent' => false, 'has_printed' => false, 'print_count' => 0]);
        }

        $consent = DB::connection('sqlsrv')
            ->table('consent_applications')
            ->where('file_number', $fileNo)
            ->where('consent_type', $consentType)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$consent) {
            return response()->json(['has_consent' => false, 'has_printed' => false, 'print_count' => 0]);
        }

        return response()->json([
            'has_consent'  => true,
            'has_printed'  => ((int) $consent->print_count > 0),
            'print_count'  => (int) $consent->print_count,
            'consent_type' => $consent->consent_type,
            'consent_id'   => $consent->id,
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\View\View
     */
    public function show($id)
    {
        $application = ConsentApplication::findOrFail($id);

        $template = strtolower($application->consent_type);
        // Map types to template names if they differ
        $templateMap = [
            'assignment' => 'assignment',
            'gift' => 'gift',
            'mortgage' => 'mortgage'
        ];

        $viewName = "consent_applications.templates." . ($templateMap[$template] ?? $template);

        return view($viewName, compact('application'));
    }

    /**
     * Update the print log and stats.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function logPrint(Request $request, $id)
    {
        $application = ConsentApplication::findOrFail($id);

        DB::beginTransaction();
        try {
            $isFirstPrint = $application->print_count == 0;

            // Increment print count
            $application->increment('print_count');

            // Log the print
            PrintLog::create([
                'reference_number' => $application->file_number,
                'document_type' => 'Application for Consent (' . $application->consent_type . ')',
                'print_type' => 'Individual',
                'status' => $isFirstPrint ? 'Original' : 'Duplicate',
                'user_id' => Auth::id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'print_count' => $application->print_count,
                'is_certified' => $application->print_count > 1
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error logging print: ' . $e->getMessage()
            ], 500);
        }
    }
}
