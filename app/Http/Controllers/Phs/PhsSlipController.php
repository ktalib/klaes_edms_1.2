<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LegalSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhsSlipController extends Controller
{
    protected LegalSearchService $searchService;

    public function __construct(LegalSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * JSON payload for the search slip (reuses the shared report engine).
     */
    public function data(Request $request)
    {
        $result = $this->searchService->buildPrintReport($request->query());
        return response()->json($result['payload'], $result['status']);
    }

    /**
     * Server-rendered certified search slip (printable).
     */
    public function print(Request $request)
    {
        $member = Auth::guard('phs')->user();
        $institution = $member->institution;

        $result = $this->searchService->buildPrintReport($request->query());

        if (($result['status'] ?? 500) !== 200) {
            abort(404, $result['payload']['message'] ?? 'No records found for this file.');
        }

        return view('phs.print.slip', [
            'data' => $result['payload']['data'],
            'institution' => $institution,
            'member' => $member,
            'reference_no' => trim((string) $request->query('reference_no', '')),
            'authorizedSignatureSrc' => $this->directorDeedsSignature(),
        ]);
    }

    /**
     * Resolve the Authorized Signatory (Director Deeds) signature for the
     * certified search slip.
     *
     * Pulls the signature image of the Director Deeds in the Lands department
     * (department_id = 8) and returns it as an inline base64 data URI so it
     * embeds reliably when the slip is printed/saved as PDF.
     */
    private function directorDeedsSignature(): ?string
    {
        $path = User::where('department_id', 8)
            ->where('rank', 'Director Deeds')
            ->value('signature');

        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['data:', 'http://', 'https://'])) {
            return $path;
        }

        // Normalise to a Storage path (DB stores e.g. "public/signing_officer_signatures/xxx.png").
        $candidates = [$path];
        if (Str::startsWith($path, '/storage/')) {
            $candidates[] = 'public/' . ltrim(substr($path, 9), '/');
        } elseif (Str::startsWith($path, 'storage/')) {
            $candidates[] = 'public/' . ltrim(substr($path, 8), '/');
        } elseif (!Str::startsWith($path, 'public/')) {
            $candidates[] = 'public/' . ltrim($path, '/');
        }

        foreach (array_unique($candidates) as $candidate) {
            if (!Storage::exists($candidate)) {
                continue;
            }

            try {
                $mimeType = Storage::mimeType($candidate) ?: 'image/png';
                return 'data:' . $mimeType . ';base64,' . base64_encode(Storage::get($candidate));
            } catch (\Throwable $e) {
                // Fall through to URL-based rendering.
            }
        }

        if (Str::startsWith($path, 'public/')) {
            return Storage::url($path);
        }

        return asset('storage/' . ltrim($path, '/'));
    }
}
