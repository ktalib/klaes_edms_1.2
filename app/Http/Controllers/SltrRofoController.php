<?php

namespace App\Http\Controllers;

use App\Models\SltrRecommendation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SltrRofoController extends Controller
{
    public function index(Request $request)
    {
        $query = SltrRecommendation::with('creator')
            ->where('status', SltrRecommendation::STATUS_APPROVED);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('sltr_number', 'LIKE', "%{$s}%")
                  ->orWhere('applicant_name', 'LIKE', "%{$s}%")
                  ->orWhere('location', 'LIKE', "%{$s}%");
            });
        }

        $recommendations = $query->latest()->paginate(20);

        $stats = [
            'total_eligible'      => SltrRecommendation::where('status', SltrRecommendation::STATUS_APPROVED)->count(),
            'pending_generation'  => SltrRecommendation::where('status', SltrRecommendation::STATUS_APPROVED)
                                        ->where('rofo_status', SltrRecommendation::ROFO_PENDING)->count(),
            'generated'           => SltrRecommendation::where('rofo_status', SltrRecommendation::ROFO_GENERATED)->count(),
            'total_ground_rent'   => SltrRecommendation::where('rofo_status', SltrRecommendation::ROFO_GENERATED)->sum('ground_rent'),
        ];

        $PageTitle = 'SLTR RofO Management';

        return view('sltr_rofos.index', compact('recommendations', 'stats', 'PageTitle'));
    }

    public function generate(Request $request, $id)
    {
        $rec = SltrRecommendation::findOrFail($id);

        if ($rec->status !== SltrRecommendation::STATUS_APPROVED) {
            return response()->json(['success' => false, 'message' => 'Recommendation must be approved before generating ROFO.'], 403);
        }

        $validated = $request->validate([
            'rofo_director_survey'  => 'nullable|string|in:YES,NO',
            'rofo_licensed_surveyor'=> 'nullable|string|in:YES,NO',
            'rofo_date_generated'   => 'nullable|date',
            'rofo_time_generated'   => 'nullable|string',
        ]);

        $generatedAt = now();
        if ($request->filled('rofo_date_generated')) {
            $time = $request->filled('rofo_time_generated') ? $request->rofo_time_generated : '00:00';
            $generatedAt = \Carbon\Carbon::parse($request->rofo_date_generated . ' ' . $time);
        }

        $rec->update(array_merge($validated, [
            'rofo_status'       => SltrRecommendation::ROFO_GENERATED,
            'rofo_generated_at' => $generatedAt,
            'rofo_date_generated'=> $request->rofo_date_generated ?? now()->toDateString(),
            'updated_by'        => Auth::id(),
        ]));

        return response()->json(['success' => true, 'message' => 'SLTR RofO generated successfully.']);
    }

    public function print(Request $request, $id)
    {
        $recommendation = SltrRecommendation::findOrFail($id);

        if ($recommendation->rofo_status !== SltrRecommendation::ROFO_GENERATED) {
            abort(403, 'ROFO must be generated before printing.');
        }

        return view('sltr_rofos.templates.rofo_print', compact('recommendation'));
    }

    public function logPrint(Request $request, $id)
    {
        $recommendation = SltrRecommendation::findOrFail($id);

        $recommendation->increment('rofo_print_count');

        return response()->json(['success' => true]);
    }
}
