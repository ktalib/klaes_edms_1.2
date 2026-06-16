<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\FileSearchRequest;
use App\Services\FileLocationResolver;
use App\Services\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SCB Monitor (mobile-only file searcher) endpoints for the Quick Search &
 * File Location module. Reuses the shared FileLocationResolver so the mobile
 * search returns the exact same five-outcome payload as the web.
 */
class MobileFileSearchController extends Controller
{
    public function __construct(
        protected UserNotificationService $notificationService
    ) {}

    /**
     * GET /api/mobile/files/search?q=RES-1985-1
     * Quick search — same engine as web, minimal fields for mobile.
     */
    public function search(Request $request, FileLocationResolver $resolver): JsonResponse
    {
        $q = trim((string) $request->get('q', $request->get('query', '')));
        if ($q === '') {
            return response()->json(['success' => false, 'message' => 'Provide a file number.'], 422);
        }

        $result = $resolver->resolve($q);
        $resolver->persist($result);

        $fileTitle = $result['tracker']->file_title
            ?? $result['indexing']->file_title
            ?? null;

        return response()->json([
            'success' => true,
            'data' => [
                'file_number'      => $result['file_number'],
                'file_title'       => $fileTitle,
                'status'           => $result['status'],
                'registry'         => $result['registry'],
                'current_location' => $result['current_location'],
                'rack_shelf'       => $result['rack_shelf'],
                'next_action'      => $result['next_action'],
                'slip_variant'     => $result['slip_variant'],
                'can_send_fr'      => (bool) ($result['can_send_fr'] ?? false),
                'can_log'          => (bool) ($result['can_log'] ?? false),
                'is_blind'         => (bool) ($result['is_blind'] ?? false),
            ],
        ]);
    }

    /**
     * GET /api/mobile/file-requests
     * Open File Requests for the authenticated SCB Monitor (assigned to them
     * or unassigned/broadcast).
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->isScbMonitor($request)) {
            return response()->json(['success' => false, 'message' => 'Not authorized — SCB Monitors only.'], 403);
        }

        $userId = $request->user()->id;

        $requests = FileSearchRequest::open()
            ->where(function ($q) use ($userId) {
                $q->whereNull('assigned_monitor_id')
                    ->orWhere('assigned_monitor_id', $userId);
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'request_no', 'file_number', 'file_title', 'current_location', 'status', 'resolved_status', 'source', 'created_at']);

        $data = $requests->map(function (FileSearchRequest $fr) {
            return array_merge([
                'id'               => $fr->id,
                'request_no'       => $fr->request_no,
                'file_number'      => $fr->file_number,
                'file_title'       => $fr->file_title,
                'current_location' => $fr->current_location,
                'status'           => $fr->status,
            ], $this->requestTypeMeta($fr));
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/mobile/file-requests/log
     * Full FSR log for the authenticated SCB Monitor — every File Search Request
     * routed to them (assigned or broadcast), regardless of status, with the
     * requester, file location, status and outcome. Optional ?status= filter.
     */
    public function log(Request $request): JsonResponse
    {
        if (!$this->isScbMonitor($request)) {
            return response()->json(['success' => false, 'message' => 'Not authorized — SCB Monitors only.'], 403);
        }

        $userId = $request->user()->id;

        $query = FileSearchRequest::with([
                'requester:id,first_name,last_name',
                'responder:id,first_name,last_name',
            ])
            ->where(function ($q) use ($userId) {
                $q->whereNull('assigned_monitor_id')
                    ->orWhere('assigned_monitor_id', $userId);
            });

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $rows = $query->orderByDesc('id')->limit(200)->get();

        $data = $rows->map(function (FileSearchRequest $fr) {
            $req  = $fr->requester;
            $resp = $fr->responder;

            return array_merge($this->requestTypeMeta($fr), [
                'id'               => $fr->id,
                'request_no'       => $fr->request_no,
                'file_number'      => $fr->file_number,
                'file_title'       => $fr->file_title,
                'requester'        => $req ? trim($req->first_name . ' ' . $req->last_name) : '—',
                'current_location' => $fr->current_location,
                'status'           => $fr->status,
                'feedback_note'    => $fr->feedback_note,
                'responder'        => $resp ? trim($resp->first_name . ' ' . $resp->last_name) : null,
                'created_at'       => optional($fr->created_at)->format('Y-m-d H:i'),
                'responded_at'     => optional($fr->responded_at)->format('Y-m-d H:i'),
            ]);
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * DELETE /api/mobile/file-requests/{id}
     * Remove a File Search Request from the SCB Monitor's open inbox.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$this->isScbMonitor($request)) {
            return response()->json(['success' => false, 'message' => 'Not authorized — SCB Monitors only.'], 403);
        }

        $fr = FileSearchRequest::find($id);
        if (!$fr) {
            return response()->json(['success' => false, 'message' => 'File Request not found.'], 404);
        }

        $fr->delete();

        return response()->json(['success' => true, 'message' => 'File Request deleted.']);
    }

    /**
     * Derive the display type for an FSR: DFR (raised from a Digital File Request),
     * Blind Request (non-indexed file), or Open Request.
     *
     * @return array{request_type:string, is_blind:bool, is_dfr:bool}
     */
    protected function requestTypeMeta(FileSearchRequest $fr): array
    {
        if ($fr->source === FileSearchRequest::SOURCE_DFR) {
            return ['request_type' => 'DFR', 'is_blind' => false, 'is_dfr' => true];
        }

        $isBlind = in_array($fr->resolved_status, [
            FileLocationResolver::STATUS_PENDING_FILE,
            FileLocationResolver::STATUS_BLIND_REQUEST_SENT,
        ], true);

        return [
            'request_type' => $isBlind ? 'Blind Request' : 'Open Request',
            'is_blind'     => $isBlind,
            'is_dfr'       => false,
        ];
    }

    /** A user is an SCB Monitor when users.fr_permissions = 'SCB'. */
    protected function isScbMonitor(Request $request): bool
    {
        return ($request->user()->fr_permissions ?? '') === 'SCB';
    }

    /**
     * POST /api/mobile/file-requests/{id}/respond
     * Body: { result: found|not_found, note?: string }
     */
    public function respond(Request $request, int $id, FileLocationResolver $resolver): JsonResponse
    {
        if (!$this->isScbMonitor($request)) {
            return response()->json(['success' => false, 'message' => 'Not authorized — SCB Monitors only.'], 403);
        }

        $validated = $request->validate([
            'result' => 'required|in:found,not_found',
            'note'   => 'nullable|string|max:1000',
        ]);

        $fr = FileSearchRequest::find($id);
        if (!$fr) {
            return response()->json(['success' => false, 'message' => 'File Request not found.'], 404);
        }

        $found = $validated['result'] === 'found';
        $fr->forceFill([
            'status'              => $found ? FileSearchRequest::STATUS_FOUND : FileSearchRequest::STATUS_NOT_FOUND,
            'feedback_note'       => $validated['note'] ?? null,
            'assigned_monitor_id' => $fr->assigned_monitor_id ?: $request->user()->id,
            'responded_by'        => $request->user()->id,
            'responded_at'        => now(),
        ])->save();

        // Combine the originating location with the SCB result into the outcome
        // status (IN_ARCHIVE_FOUND / IN_POOL_OFFICE_NOT_FOUND, …) and store it on
        // the matching file_indexings row so the Front Desk Quick Search reflects it.
        $resolved      = $resolver->resolve($fr->file_number);
        $outcomeStatus = $resolver->combineScbOutcome($fr->resolved_status, $found);

        if ($indexing = $resolved['indexing']) {
            $indexing->forceFill([
                'tracking_status'        => $outcomeStatus,
                'location_status_manual' => now(),   // make the resolver honour this stored outcome
            ])->save();
        }

        // Not-found on a non-indexed (or uncaptured) file → print Missing/Refer slip.
        $slipVariant = $found ? null : 'refer_registry';

        // Notify the original requester of the outcome.
        if ($fr->requester_user_id) {
            $this->notificationService->create(
                $fr->requester_user_id,
                'file_search_request',
                "File Request {$fr->request_no}: " . ($found ? 'Found' : 'Not Found'),
                "File {$fr->file_number} was marked " . ($found ? 'FOUND' : 'NOT FOUND') . ' by the SCB Monitor.'
                    . (!empty($validated['note']) ? " Note: {$validated['note']}" : ''),
                ['request_id' => $fr->id, 'request_no' => $fr->request_no, 'file_number' => $fr->file_number],
                ['module' => 'file_search_request']
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status'         => $fr->status,
                'outcome_status' => $outcomeStatus,
                'next_action'    => $found ? 'Front Desk can now Log the file' : 'Refer to Original Registry',
                'slip_variant'   => $slipVariant,
            ],
            'message' => 'Feedback recorded.',
        ]);
    }
}
