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
 *  - Adding an entry (manual form)
 *  - Editing / updating an entry
 *  - Deleting an entry
 *  - Row info lookup (fetchrowinfo) for edit modal
 *  - CSV import (max 100 records)
 *  - CSV template download
 */
class AllocationListEntryController extends Controller
{
    // ─── View ────────────────────────────────────────────────────────────────

    /**
     * Return the main index view with data needed to populate form dropdowns.
     */
    public function index(Request $request)
    {
        $pageTitle = __('Allocation List');
        $pageDescription = __('Manage and track land allocation records');

        // Titles dropdown
        $titles = DB::connection('sqlsrv')
            ->table('titles')
            ->orderBy('sort_order')
            ->get();

        // Kano LGAs
        $lgas = DB::connection('sqlsrv')
            ->table('StatLGAs')
            ->join('States', 'StatLGAs.StateID', '=', 'States.StateID')
            ->where('States.StateName', 'Kano')
            ->orderBy('LGAName')
            ->pluck('StatLGAs.LGAName');

        return view('allocation_list_entry.index', compact('titles', 'lgas', 'pageTitle', 'pageDescription'));
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
                'title'        => 'nullable|string|max:50',
                'first_name'   => 'required|string|max:100',
                'middle_name'  => 'nullable|string|max:100',
                'last_name'    => 'required|string|max:100',
                'plot_number'  => 'nullable|string|max:50',
                'district'     => 'nullable|string|max:100',
                'lga'          => 'nullable|string|max:100',
                'state'        => 'nullable|string|max:100',
                'allottee_address' => 'nullable|string',
            ]);

            $record = AllocationListEntry::find($id);
            if (!$record) {
                return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
            }

            $affected = $record->update([
                'title'        => strtoupper(trim($validated['title'] ?? '')),
                'first_name'   => strtoupper(trim($validated['first_name'])),
                'middle_name'  => strtoupper(trim($validated['middle_name'] ?? '')),
                'last_name'    => strtoupper(trim($validated['last_name'])),
                'plot_number'  => strtoupper(trim($validated['plot_number'] ?? '')),
                'district'     => strtoupper(trim($validated['district'] ?? '')),
                'lga'          => $validated['lga'] ?? null,
                'state'        => $validated['state'] ?? 'Kano',
                'allottee_address' => strtoupper(trim($validated['allottee_address'] ?? '')),
                'updated_by'   => Auth::id(),
            ]);

            if (!$affected) {
                return response()->json(['success' => false, 'message' => 'No changes made.'], 404);
            }

            return response()->json(['success' => true, 'message' => 'Entry updated successfully.']);
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
