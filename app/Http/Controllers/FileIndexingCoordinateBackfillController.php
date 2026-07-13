<?php

namespace App\Http\Controllers;

use App\Services\FileIndexingCoordinateBackfillService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FileIndexingCoordinateBackfillController extends Controller
{
    public function index(FileIndexingCoordinateBackfillService $service)
    {
        return view('fileindexing.backfill-coordinates', [
            'remaining' => $service->remainingCount(),
        ]);
    }

    public function run(Request $request, FileIndexingCoordinateBackfillService $service): JsonResponse
    {
        // Each row costs ~100ms throttle + a live Google round-trip, so a web
        // request (unlike the CLI command, which has no time limit) needs a
        // smaller batch plus headroom above PHP's default 60s execution limit.
        set_time_limit(180);

        $limit = (int) $request->input('limit', 30);
        $limit = max(1, min(50, $limit));
        $afterId = $request->input('after_id');
        $afterId = $afterId !== null ? (int) $afterId : null;

        $result = $service->runBatch($limit, false, false, $afterId);

        if (!empty($result['key_missing'])) {
            return response()->json([
                'error' => 'GOOGLE_GEOCODING_API_KEY is not configured.',
            ], 422);
        }

        return response()->json($result);
    }
}
