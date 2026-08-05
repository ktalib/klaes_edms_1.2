<?php

namespace App\Http\Controllers;

use App\Models\LandRecommendationBatchDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Autosave endpoints for the Plot Subdivision batch capture.
 *
 * A subdivision batch can carry 100+ children, and keying them takes longer than
 * a session lasts. The form posts itself here every few seconds so a timeout, a
 * 419 on submit, or a closed tab costs nothing: the draft is picked back up from
 * the "Resume a draft" list on the capture form.
 *
 * Nothing is validated the way a real batch is — a draft is by definition
 * half-filled. The only checks here are the ones that protect the *store*
 * (ownership, size, JSON shape); the recommendation rules stay in
 * LandRecommendationController::storeBatch(), which is still the only path that
 * writes a recommendation.
 */
class LandRecommendationBatchDraftController extends Controller
{
    /**
     * Hard ceiling on a single draft. A 150-child batch serialises to roughly
     * 120 KB, so this is generous; it exists to stop a runaway client from
     * writing megabytes into the table on every keystroke.
     */
    private const MAX_PAYLOAD_BYTES = 4 * 1024 * 1024;

    /**
     * The current user's open drafts, for the resume list on the capture form.
     */
    public function index(Request $request)
    {
        $drafts = LandRecommendationBatchDraft::query()
            ->ownedBy(Auth::id())
            ->open()
            ->orderByDesc('last_saved_at')
            ->limit(25)
            ->get()
            ->map(fn ($d) => $d->toClientArray());

        return response()->json(['success' => true, 'drafts' => $drafts]);
    }

    /**
     * Create or overwrite a draft. Called on a debounce while the user types, so
     * it must stay cheap: one upsert, no joins, no lookups.
     *
     * The response also carries a fresh CSRF token. The autosave is what keeps
     * the session alive during a long capture, so handing the token back on every
     * save is what stops the eventual submit from dying on a 419.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'draft_key'         => 'required|string|max:40',
            'application_type'  => 'nullable|string|max:60',
            'mother_file_no'    => 'nullable|string|max:100',
            'children_total'    => 'nullable|integer|min:0',
            'children_selected' => 'nullable|integer|min:0',
            // Sent as a JSON string rather than a nested array: a 100-child batch
            // is several thousand form fields, well past max_input_vars (1000 by
            // default), and PHP drops the overflow silently.
            'payload'           => 'required|string',
        ]);

        if (strlen($validated['payload']) > self::MAX_PAYLOAD_BYTES) {
            return response()->json([
                'success' => false,
                'message' => 'This draft is too large to autosave.',
            ], 413);
        }

        $payload = json_decode($validated['payload'], true);
        if (!is_array($payload)) {
            return response()->json([
                'success' => false,
                'message' => 'Draft payload was not valid JSON.',
            ], 422);
        }

        $draft = LandRecommendationBatchDraft::query()
            ->where('draft_key', $validated['draft_key'])
            ->first();

        // A draft belongs to the officer who keyed it. Reusing someone else's key
        // must not hand over their capture, so the write is refused outright
        // rather than quietly starting a second row on the same key (the column
        // is unique, so that would fail anyway — just less clearly).
        if ($draft && $draft->user_id && (int) $draft->user_id !== (int) Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'This draft belongs to another user.',
            ], 403);
        }

        // A submitted draft is history. If the browser autosaves once more after
        // the batch was saved (an in-flight request that lost the race with the
        // redirect), that late write must not resurrect it into the resume list.
        if ($draft && $draft->status !== LandRecommendationBatchDraft::STATUS_OPEN) {
            return response()->json([
                'success'  => true,
                'ignored'  => true,
                'status'   => $draft->status,
                'message'  => 'This draft has already been ' . $draft->status . '.',
                'csrf'     => csrf_token(),
            ]);
        }

        $attributes = [
            'user_id'           => Auth::id(),
            'application_type'  => $validated['application_type'] ?? null,
            'mother_file_no'    => $validated['mother_file_no'] ?? null,
            'children_total'    => $validated['children_total'] ?? 0,
            'children_selected' => $validated['children_selected'] ?? 0,
            'payload'           => $payload,
            'status'            => LandRecommendationBatchDraft::STATUS_OPEN,
            'last_saved_at'     => now(),
        ];

        // Keep the copy this save is about to replace. Autosave overwrites the
        // draft every few seconds, so without this any accident that empties the
        // table on screen is written over the good copy within one debounce and
        // the work is simply gone.
        //
        // Only a save that *loses* rows banks a rollback point. Ordinary typing
        // must not push the good copy out — it would be replaced on the very next
        // keystroke, which is precisely when it is still needed.
        if ($draft && !empty($draft->payload)) {
            $before = count($draft->payload['children'] ?? []);
            $after  = count($payload['children'] ?? []);

            if ($after < $before) {
                $attributes['payload_previous']        = $draft->payload;
                $attributes['previous_children_total'] = $before;
                $attributes['previous_saved_at']       = $draft->last_saved_at;
            }
        }

        try {
            $draft = LandRecommendationBatchDraft::updateOrCreate(
                ['draft_key' => $validated['draft_key']],
                $attributes
            );
        } catch (\Throwable $e) {
            Log::error('Batch draft autosave failed', [
                'draft_key' => $validated['draft_key'],
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not save the draft: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'draft'   => $draft->toClientArray(),
            'csrf'    => csrf_token(),
        ]);
    }

    /**
     * The full payload of one draft, for restoring it into the form.
     */
    public function show(Request $request, string $draftKey)
    {
        $draft = $this->findOwned($draftKey);

        if (!$draft) {
            return response()->json([
                'success' => false,
                'message' => 'Draft not found.',
            ], 404);
        }

        $data = $draft->toClientArray(true);

        // ?previous=1 asks for the copy banked before the last save that lost rows
        // — the way back from a restore or a reload that emptied the table.
        if ($request->boolean('previous')) {
            if (empty($draft->payload_previous)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This draft has no earlier version.',
                ], 404);
            }
            $data['payload']     = $draft->payload_previous;
            $data['is_previous'] = true;
        }

        return response()->json([
            'success' => true,
            'draft'   => $data,
        ]);
    }

    /**
     * Throw a draft away. Kept as a status change rather than a delete so a draft
     * discarded by accident can still be recovered from the table.
     */
    public function destroy(Request $request, string $draftKey)
    {
        $draft = $this->findOwned($draftKey);

        if (!$draft) {
            return response()->json([
                'success' => false,
                'message' => 'Draft not found.',
            ], 404);
        }

        $draft->status = LandRecommendationBatchDraft::STATUS_DISCARDED;
        $draft->save();

        return response()->json(['success' => true]);
    }

    private function findOwned(string $draftKey): ?LandRecommendationBatchDraft
    {
        return LandRecommendationBatchDraft::query()
            ->where('draft_key', $draftKey)
            ->where(function ($q) {
                // Legacy rows saved before a user was attached stay reachable by
                // whoever holds the key.
                $q->whereNull('user_id')->orWhere('user_id', Auth::id());
            })
            ->first();
    }
}
