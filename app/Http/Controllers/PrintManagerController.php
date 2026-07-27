<?php

namespace App\Http\Controllers;

use App\Models\PrintLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PrintManagerController extends Controller
{
    /**
     * Store a new print log entry.
     * ['Original', 'Duplicate', 'Triplicate'] [colors: 'red','blue','green']
     */
    public function log(Request $request)
    {
        $request->validate([
            'reference_number' => 'required|string',
            'document_type' => 'required|string',
            'status' => 'required|string', // Original, Duplicate, Triplicate
            'print_type' => 'required|string', // Individual, Batch
        ]);

        $log = PrintLog::create([
            'reference_number' => $request->reference_number,
            'document_type' => $request->document_type,
            'print_type' => $request->print_type,
            'status' => $request->status,
            'user_id' => Auth::id(),
        ]);

        if (str_contains($request->document_type, 'Legal Search')) {
            \App\Models\LegalSearchLog::where('search_value', $request->reference_number)
                ->where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->limit(1)
                ->update(['printed' => true]);
        }

        return response()->json([
            'success' => true,
            'data' => $log
        ]);
    }

    /**
     * Check the current print status for a document.
     */
    public function checkStatus(Request $request)
    {
        $request->validate([
            'reference_number' => 'required|string',
            'document_type' => 'required|string',
        ]);

        $logs = PrintLog::where('reference_number', $request->reference_number)
            ->where('document_type', $request->document_type)
            ->get();

        $isSingleStep = in_array($request->document_type, ['Recommendation For Grant', 'ST CofO', 'Legal Search Pay-Per-Search', 'Legal Search Online', 'Legal Search Official']);

        $status = [
            'original' => $logs->where('status', 'Original')->count(),
            'duplicate' => $logs->where('status', 'Duplicate')->count(),
            'triplicate' => $logs->where('status', 'Triplicate')->count(),
            'completed' => $isSingleStep
                ? $logs->where('status', 'Original')->count() > 0
                : $logs->whereIn('status', ['Original', 'Duplicate', 'Triplicate'])->groupBy('status')->count() >= 3
        ];

        return response()->json([
            'success' => true,
            'status' => $status
        ]);
    }

    /**
     * Store multiple print log entries in batch.
     */
    public function batchLog(Request $request)
    {
        $request->validate([
            'reference_number' => 'required|string',
            'document_type' => 'required|string',
            'statuses' => 'required|array', // e.g., ['Original', 'Duplicate', 'Triplicate']
            'print_type' => 'required|string',
        ]);

        foreach ($request->statuses as $status) {
            PrintLog::create([
                'reference_number' => $request->reference_number,
                'document_type' => $request->document_type,
                'print_type' => $request->print_type,
                'status' => $status,
                'user_id' => Auth::id(),
            ]);
        }

        return response()->json([
            'success' => true
        ]);
    }



}
