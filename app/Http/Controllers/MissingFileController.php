<?php

namespace App\Http\Controllers;

use App\Models\MissingFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MissingFileController extends Controller
{
    /**
     * Display the Missing Files capture page.
     */
    public function index(Request $request)
    {
        $module = $request->get('url', '');

        $perPage = 25;
        $paginator = MissingFile::orderByDesc('id')->paginate($perPage);

        $missingFiles = $paginator->items();
        $pagination = [
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
        ];

        return view('missing_files.index', compact('module', 'missingFiles', 'pagination'));
    }

    /**
     * Return the missing files list as JSON (used to refresh the table).
     */
    public function data(Request $request)
    {
        $query = MissingFile::query();

        if ($status = trim((string) $request->get('status', ''))) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->get('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('file_number', 'like', '%' . $search . '%')
                  ->orWhere('file_title', 'like', '%' . $search . '%')
                  ->orWhere('full_label', 'like', '%' . $search . '%');
            });
        }

        $perPage = (int) $request->get('per_page', 25);
        $perPage = $perPage > 0 && $perPage <= 200 ? $perPage : 25;

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
        ]);
    }

    /**
     * Store a newly reported missing file.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'file_number'       => ['required', 'string', 'max:100'],
            'file_title'        => ['nullable', 'string', 'max:255'],
            'property_location' => ['nullable', 'string', 'max:255'],
            'tracking_id'       => ['nullable', 'string', 'max:100'],
            'archive_registry' => ['nullable', 'string', 'max:50'],
            'rack_primary'     => ['nullable', 'string', 'max:10'],
            'rack_secondary'   => ['nullable', 'string', 'max:10'],
            'shelf_number'     => ['nullable', 'string', 'max:10'],
            'full_label'       => ['nullable', 'string', 'max:30'],
            'remarks'          => ['nullable', 'string'],
        ]);

        try {
            $user = Auth::user();

            // Normalize shelf location from full label (e.g. "A 12" -> "A12").
            $shelfLocation = $validated['full_label'] ?? null;
            if ($shelfLocation !== null) {
                $shelfLocation = strtoupper(str_replace(['-', ' ', '/', '\\'], '', trim($shelfLocation)));
                $shelfLocation = $shelfLocation === '' ? null : $shelfLocation;
            }

            $missing = MissingFile::create([
                'file_number'      => trim($validated['file_number']),
                'file_title'       => $validated['file_title'] ?? null,
                'property_location'=> $validated['property_location'] ?? null,
                'tracking_id'      => $validated['tracking_id'] ?? null,
                'archive_registry' => $validated['archive_registry'] ?? null,
                'rack_primary'     => $validated['rack_primary'] ?? null,
                'rack_secondary'   => $validated['rack_secondary'] ?? null,
                'shelf_number'     => $validated['shelf_number'] ?? null,
                'full_label'       => $validated['full_label'] ?? null,
                'shelf_location'   => $shelfLocation,
                'reported_by'      => $user->id ?? null,
                'reported_by_name' => $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : null,
                'remarks'          => $validated['remarks'] ?? null,
                'status'           => MissingFile::STATUS_MISSING,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File recorded as missing.',
                'data'    => $missing,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('MissingFileController::store failed', [
                'file_number' => $validated['file_number'] ?? null,
                'message'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to record the missing file.',
            ], 500);
        }
    }

    /**
     * Mark a missing file as found.
     */
    public function markFound(Request $request, $id)
    {
        $missing = MissingFile::find($id);

        if (! $missing) {
            return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
        }

        $user = Auth::user();

        $missing->update([
            'status'        => MissingFile::STATUS_FOUND,
            'found_at'      => now(),
            'found_by'      => $user->id ?? null,
            'found_by_name' => $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File marked as found.',
            'data'    => $missing,
        ]);
    }

    /**
     * Delete a missing file record.
     */
    public function destroy($id)
    {
        $missing = MissingFile::find($id);

        if (! $missing) {
            return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
        }

        $missing->delete();

        return response()->json(['success' => true, 'message' => 'Record deleted.']);
    }
}
