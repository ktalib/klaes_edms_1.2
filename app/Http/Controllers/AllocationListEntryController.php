<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\AllocationListEntry;

/**
 * AllocationListEntryController
 *
 * Handles CRUD operations for the Allocation List (allocation_list_stage table).
 * Supports:
 *  - Listing entries (DataTable-ready)
 *  - Capturing an EXISTING allocation (file number + name + location + year)
 *  - File number lookup, which backfills file title and location
 *  - Editing / updating an entry
 *  - Deleting an entry
 *  - Row info lookup (fetchrowinfo) for edit modal
 *  - CSV import (max 100 records)
 *  - CSV template download
 */
class AllocationListEntryController extends Controller
{
    /** The field names read badly on the form ("the file no field"). */
    private const VALIDATION_MESSAGES = [
        'file_no.required'       => 'File number is required.',
        'allottee_name.required' => 'Name is required.',
        'allocation_year.digits' => 'Year must be a 4-digit year.',
    ];

    // ─── View ────────────────────────────────────────────────────────────────

    /**
     * Return the main index view with data needed to populate form dropdowns.
     */
    public function index(Request $request)
    {
        $pageTitle = __('Allocation List');
        $pageDescription = __('Capture and track existing land allocations');

        // The capture form takes a file number, a name and the details that come
        // back with the file, so the old titles / LGA dropdowns are gone and
        // their queries with them.
        return view('allocation_list_entry.index', compact('pageTitle', 'pageDescription'));
    }

    // ─── AJAX: all entries as JSON ────────────────────────────────────────────

    /**
     * Return all allocation list entries as JSON (for DataTable client-side).
     */
    public function getAllEntries(Request $request)
    {
        try {
            $query = AllocationListEntry::query();

            $records = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data'    => $records,
                'total'   => $records->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('AllocationListEntry getAllEntries: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── API: single row info (for edit modal) ────────────────────────────────

    /**
     * Fetch info for a single row – used by the AllocationListEntryApi and
     * called directly via the route /allocation-list/fetchrowinfo/{id}.
     */
    public function fetchrowinfo($id)
    {
        try {
            $record = AllocationListEntry::find($id);

            if (!$record) {
                return response()->json(['success' => false, 'message' => 'Record not found'], 404);
            }

            return response()->json(['success' => true, 'data' => $record]);
        } catch (\Exception $e) {
            Log::error('AllocationListEntry fetchrowinfo: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── Existing allocation capture ──────────────────────────────────────────

    /**
     * Resolve a file number into the details the capture form backfills:
     * file title, location and the year encoded in the number itself.
     */
    public function lookupFile(Request $request)
    {
        $fileNo = strtoupper(trim((string) $request->get('file_no', '')));

        if ($fileNo === '') {
            return response()->json(['success' => false, 'message' => 'File number is required.'], 422);
        }

        try {
            $details = $this->resolveFileDetails($fileNo);

            return response()->json([
                'success' => true,
                'found'   => $details['found'],
                'data'    => [
                    'file_no'         => $fileNo,
                    'file_title'      => $details['file_title'],
                    'location'        => $details['location'],
                    'allocation_year' => $this->detectYearFromFileNumber($fileNo),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('AllocationListEntry lookupFile: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Capture one existing allocation. Saved one at a time over AJAX so the
     * operator can keep the form open and keep entering.
     */
    public function storeExisting(Request $request)
    {
        try {
            $validated = $request->validate([
                'file_no'         => 'required|string|max:100',
                'allottee_name'   => 'required|string|max:255',
                'file_title'      => 'nullable|string|max:255',
                'location'        => 'nullable|string|max:255',
                'allocation_year' => 'nullable|digits:4',
            ], self::VALIDATION_MESSAGES);

            $fileNo = strtoupper(trim($validated['file_no']));
            $name   = $this->normalizeName($validated['allottee_name']);

            $fileTitle = strtoupper(trim((string) ($validated['file_title'] ?? '')));
            $location  = strtoupper(trim((string) ($validated['location'] ?? '')));

            // The form backfills these on selection, but a number typed straight
            // in (or a lookup that failed) still deserves whatever we can resolve.
            if ($fileTitle === '' || $location === '') {
                $details = $this->resolveFileDetails($fileNo);
                $fileTitle = $fileTitle !== '' ? $fileTitle : strtoupper((string) $details['file_title']);
                $location  = $location !== '' ? $location : strtoupper((string) $details['location']);
            }

            // The year is derived from the number, so only trust a supplied value
            // when the number itself yields nothing to derive.
            $year = $this->detectYearFromFileNumber($fileNo) ?? ($validated['allocation_year'] ?? null);

            $nameParts = $this->splitName($name);

            $record = AllocationListEntry::create([
                'file_no'         => $fileNo,
                'file_title'      => $fileTitle ?: null,
                'allottee_name'   => $name,
                'location'        => $location ?: null,
                'allocation_year' => $year,
                // Keep the legacy name columns populated so the MLS generator and
                // the CSV exports keep reading these rows.
                'first_name'      => $nameParts['first_name'],
                'middle_name'     => $nameParts['middle_name'],
                'last_name'       => $nameParts['last_name'],
                // These rows describe allocations that already happened, so they
                // must never be offered up again as unallocated.
                'is_allocated'    => 1,
                'created_by'      => Auth::id(),
                'updated_by'      => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Allocation for {$fileNo} saved.",
                'data'    => $record->fresh(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('AllocationListEntry storeExisting: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Pull the file title and location for a file number out of the registries.
     *
     * dbo.fileNumber first (the canonical register), then file_indexings, then
     * st_file_numbers — each one only filling in what the previous left blank.
     *
     * @return array{found:bool, file_title:?string, location:?string}
     */
    private function resolveFileDetails(string $fileNo): array
    {
        $fileNo = trim($fileNo);
        $found  = false;
        $title  = null;
        $location = null;

        if ($fileNo === '') {
            return ['found' => false, 'file_title' => null, 'location' => null];
        }

        // A temporary number is stored without its "(T)" marker in some columns,
        // so search for both forms. See the temp-number lookup notes on the
        // Legal Search side — a literal-only match misses silently.
        $variants = array_values(array_unique(array_filter([
            $fileNo,
            trim((string) preg_replace('/\(\s*T\s*\)\s*$/i', '', $fileNo)),
        ], fn($v) => $v !== '')));

        $matchAny = function ($query, array $columns) use ($variants) {
            return $query->where(function ($q) use ($columns, $variants) {
                foreach ($columns as $column) {
                    foreach ($variants as $value) {
                        $q->orWhereRaw("{$column} = CAST(? AS VARCHAR(255))", [$value]);
                    }
                }
            });
        };

        try {
            $row = $matchAny(
                DB::connection('sqlsrv')->table('fileNumber')
                    ->select('FileName', 'location', 'plot_no'),
                ['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no', 'temp_file_no']
            )->orderByDesc('id')->first();

            if ($row) {
                $found    = true;
                $title    = $this->blankToNull($row->FileName);
                $location = $this->blankToNull($row->location);
            }
        } catch (\Exception $e) {
            Log::warning('AllocationListEntry resolveFileDetails (fileNumber): ' . $e->getMessage());
        }

        if ($title === null || $location === null) {
            try {
                $row = $matchAny(
                    DB::connection('sqlsrv')->table('file_indexings')
                        ->select('file_title', 'district', 'lga', 'plot_number'),
                    ['file_number', 'temp_file_no', 'mls_file_no', 'kangis_file_no', 'new_kangis_file_no']
                )->orderByDesc('id')->first();

                if ($row) {
                    $found    = true;
                    $title    = $title ?? $this->blankToNull($row->file_title);
                    $location = $location ?? $this->joinNonEmpty([$row->district, $row->lga]);
                }
            } catch (\Exception $e) {
                Log::warning('AllocationListEntry resolveFileDetails (file_indexings): ' . $e->getMessage());
            }
        }

        if ($title === null || $location === null) {
            try {
                $row = DB::connection('sqlsrv')->table('st_file_numbers')
                    ->whereIn('fileno', $variants)
                    ->orderByDesc('id')
                    ->first();

                if ($row) {
                    $found = true;
                    $title = $title ?? $this->blankToNull(
                        $row->corporate_name
                            ?: $this->joinNonEmpty([$row->first_name ?? null, $row->surname ?? null], ' ')
                    );
                    $location = $location ?? $this->joinNonEmpty([
                        $row->property_district ?? null,
                        $row->property_lga ?? null,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('AllocationListEntry resolveFileDetails (st_file_numbers): ' . $e->getMessage());
            }
        }

        return ['found' => $found, 'file_title' => $title, 'location' => $location];
    }

    /**
     * Read the allocation year out of a file number, e.g. RES-1982-2081 → 1982.
     *
     * Takes the leftmost plausible 4-digit group so a serial that happens to
     * look like a year (…-2081) never wins over the real one. Mirrored by
     * aleDetectYear() in public/js/allocation_list_entry.js.
     */
    private function detectYearFromFileNumber(?string $fileNo): ?string
    {
        $fileNo = trim((string) $fileNo);
        if ($fileNo === '') {
            return null;
        }

        $maxYear = (int) date('Y') + 1;

        foreach (preg_split('/[^0-9]+/', $fileNo) as $group) {
            if (strlen($group) !== 4) {
                continue;
            }
            $year = (int) $group;
            if ($year >= 1900 && $year <= $maxYear) {
                return $group;
            }
        }

        return null;
    }

    /**
     * Upper-case the captured name and collapse the runs of whitespace that
     * come with hand-keyed entry.
     */
    private function normalizeName(string $name): string
    {
        return strtoupper(trim((string) preg_replace('/\s+/', ' ', $name)));
    }

    /**
     * Best-effort split of a single free-text name into the legacy columns.
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return [
            'first_name'  => $parts[0] ?? null,
            'middle_name' => count($parts) > 2 ? implode(' ', array_slice($parts, 1, -1)) : null,
            'last_name'   => count($parts) > 1 ? end($parts) : null,
        ];
    }

    private function blankToNull($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function joinNonEmpty(array $values, string $glue = ', '): ?string
    {
        $filtered = array_filter(array_map(fn($v) => trim((string) $v), $values), fn($v) => $v !== '');
        return empty($filtered) ? null : implode($glue, $filtered);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    /**
     * Insert a new allocation list entry.
     */
    public function store(Request $request)
    {
        try {
            // Support both single entry or multiple entries
            $entries = [];
            
            if ($request->has('entries')) {
                // Bulk entries from the dynamic form
                $entries = $request->input('entries');
            } else {
                // Conventional single entry
                $entries[] = $request->all();
            }

            $inserted = 0;
            $errors = [];

            foreach ($entries as $index => $data) {
                // Basic validation for each entry
                if (empty($data['first_name']) || empty($data['last_name'])) {
                    $errors[] = "Entry #".($index + 1).": First Name and Last Name are required.";
                    continue;
                }

                // Construct full address if not provided
                $addressParts = [
                    !empty($data['plot_number']) ? "PLOT " . strtoupper(trim($data['plot_number'])) : '',
                    strtoupper(trim($data['district'] ?? '')),
                    strtoupper(trim($data['lga'] ?? '')),
                    strtoupper(trim($data['state'] ?? 'KANO'))
                ];
                $allotteeAddress = strtoupper(trim($data['allottee_address'] ?? ''));
                if (empty($allotteeAddress)) {
                    $allotteeAddress = implode(', ', array_filter($addressParts));
                }

                AllocationListEntry::create([
                    'title'        => strtoupper(trim($data['title'] ?? '')),
                    'first_name'   => strtoupper(trim($data['first_name'])),
                    'middle_name'  => strtoupper(trim($data['middle_name'] ?? '')),
                    'last_name'    => strtoupper(trim($data['last_name'])),
                    'plot_number'  => strtoupper(trim($data['plot_number'] ?? '')),
                    'district'     => strtoupper(trim($data['district'] ?? '')),
                    'lga'          => $data['lga'] ?? null,
                    'state'        => $data['state'] ?? 'KANO',
                    'allottee_address' => $allotteeAddress,
                    'created_by'   => Auth::id(),
                    'updated_by'   => Auth::id(),
                ]);
                $inserted++;
            }

            if ($inserted === 0 && !empty($errors)) {
                return response()->json(['success' => false, 'message' => implode(' ', $errors)], 422);
            }

            $message = $inserted > 1 ? "$inserted entries added successfully." : "Entry added successfully.";
            return response()->json(['success' => true, 'message' => $message]);

        } catch (\Exception $e) {
            Log::error('AllocationListEntry store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    /**
     * Update an existing allocation list entry.
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'file_no'         => 'required|string|max:100',
                'allottee_name'   => 'required|string|max:255',
                'file_title'      => 'nullable|string|max:255',
                'location'        => 'nullable|string|max:255',
                'allocation_year' => 'nullable|digits:4',
            ], self::VALIDATION_MESSAGES);

            $record = AllocationListEntry::find($id);
            if (!$record) {
                return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
            }

            $fileNo    = strtoupper(trim($validated['file_no']));
            $name      = $this->normalizeName($validated['allottee_name']);
            $nameParts = $this->splitName($name);

            $record->update([
                'file_no'         => $fileNo,
                'allottee_name'   => $name,
                'file_title'      => $this->blankToNull(strtoupper((string) ($validated['file_title'] ?? ''))),
                'location'        => $this->blankToNull(strtoupper((string) ($validated['location'] ?? ''))),
                'allocation_year' => $this->detectYearFromFileNumber($fileNo) ?? ($validated['allocation_year'] ?? null),
                'first_name'      => $nameParts['first_name'],
                'middle_name'     => $nameParts['middle_name'],
                'last_name'       => $nameParts['last_name'],
                'updated_by'      => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Entry updated successfully.',
                'data'    => $record->fresh(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('AllocationListEntry update: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    /**
     * Delete an allocation list entry.
     */
    public function destroy($id)
    {
        try {
            $record = AllocationListEntry::find($id);
            if (!$record) {
                return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
            }
            
            $deleted = $record->delete();

            if (!$deleted) {
                return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
            }

            return response()->json(['success' => true, 'message' => 'Entry deleted successfully.']);
        } catch (\Exception $e) {
            Log::error('AllocationListEntry destroy: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── CSV Import ───────────────────────────────────────────────────────────

    /**
     * Import bulk allocation entries from a CSV file (max 100 records).
     */
    public function importCsv(Request $request)
    {
        try {
            $request->validate([
                'records'   => 'required|array|min:1|max:100',
                'records.*' => 'array',
            ]);

            $records = $request->input('records');

            if (count($records) > 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Import limit is 100 records per batch.',
                ], 422);
            }

            $inserted = 0;
            $skipped  = 0;

            foreach ($records as $row) {
                $firstName = strtoupper(trim($row['first_name'] ?? ''));
                $lastName  = strtoupper(trim($row['last_name'] ?? ''));

                if (empty($firstName) || empty($lastName)) {
                    $skipped++;
                    continue;
                }

                AllocationListEntry::create([
                    'title'        => strtoupper(trim($row['title'] ?? '')),
                    'first_name'   => $firstName,
                    'middle_name'  => strtoupper(trim($row['middle_name'] ?? '')),
                    'last_name'    => $lastName,
                    'plot_number'  => strtoupper(trim($row['plot_number'] ?? '')),
                    'district'     => strtoupper(trim($row['district'] ?? '')),
                    'lga'          => $row['lga'] ?? null,
                    'state'        => $row['state'] ?? 'Kano',
                    'allottee_address' => strtoupper(trim($row['allottee_address'] ?? '')),
                    'created_by'   => Auth::id(),
                    'updated_by'   => Auth::id(),
                ]);

                $inserted++;
            }

            $message = "$inserted record(s) imported.";
            if ($skipped > 0) {
                $message .= " $skipped record(s) skipped (missing name).";
            }

            return response()->json(['success' => true, 'message' => $message, 'inserted' => $inserted, 'skipped' => $skipped]);
        } catch (\Exception $e) {
            Log::error('AllocationListEntry importCsv: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── CSV Template Download ────────────────────────────────────────────────

    /**
     * Download a CSV template for bulk import.
     */
    public function downloadTemplate()
    {
        $headers = ['title', 'first_name', 'middle_name', 'last_name'];

        $titles       = ['Alhaji', 'Hajiya', 'Mallam', 'Malama', 'Engr', 'Dr', 'Alhajiya'];
        $firstNames   = [
            'Musa', 'Amina', 'Sani', 'Zainab', 'Yakubu', 'Hauwa', 'Bashir', 'Hadiza',
            'Umar', 'Fatima', 'Abubakar', 'Maryam', 'Garba', 'Aisha', 'Kabiru', 'Ramatu',
            'Aminu', 'Binta', 'Aliyu', 'Jummai', 'Dauda', 'Safiya', 'Ibrahim', 'Halima',
            'Yusuf', 'Rabi', 'Haruna', 'Bilkisu', 'Bello', 'Hafsat', 'Rabiu', 'Rukayya',
            'Tukur', 'Kaltumi', 'Lawal', 'Suwaiba', 'Isah', 'Zulaiha', 'Hamisu', 'Firdausi',
            'Sulaiman', 'Sajida', 'Shehu', 'Baraka', 'Nuhu', 'Nana', 'Adamu', 'Asiya',
            'Halliru', 'Nusaiba',
        ];
        $midInitials  = ['A', 'B', 'D', 'F', 'G', 'H', 'I', 'K', 'L', 'M', 'N', 'R', 'S', 'T', 'U', 'Y', ''];
        $lastNames    = [
            'Ibrahim', 'Ahmed', 'Hassan', 'Muhammad', 'Abdullahi', 'Garba', 'Saleh', 'Lawal',
            'Usman', 'Abubakar', 'Bello', 'Suleiman', 'Musa', 'Sulaiman', 'Danjuma', 'Rabiu',
            'Aliyu', 'Maigari', 'Bayero', 'Wada', 'Ringim', 'Gaya', 'Tofa', 'Ungogo',
            'Kura', 'Gezawa', 'Tanko', 'Zango', 'Chiroma', 'Makama',
        ];
        $sample = [];
        for ($i = 0; $i < 100; $i++) {
            $sample[] = [
                $titles[$i % count($titles)],
                $firstNames[$i % count($firstNames)],
                $midInitials[$i % count($midInitials)],
                $lastNames[$i % count($lastNames)],
            ];
        }

        $filename = 'allocation_list_template_' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($headers, $sample) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
            fputcsv($f, $headers);
            foreach ($sample as $row) {
                fputcsv($f, $row);
            }
            fclose($f);
        }, $filename, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Get all unallocated entries (is_allocated = 0) for the MLS Generator dropdown.
     */
    public function getUnallocatedEntries()
    {
        try {
            $entries = AllocationListEntry::where('is_allocated', 0)
                ->orderBy('first_name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $entries
            ]);
        } catch (\Exception $e) {
            Log::error('AllocationListEntry getUnallocatedEntries: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
