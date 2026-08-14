<?php

namespace App\Services;

use App\Mail\LegalSearchRequestApproved;
use App\Mail\LegalSearchRequestPendingApproval;
use App\Mail\LegalSearchRequestRejected;
use App\Mail\LegalSearchRequestSubmitted;
use App\Models\LandOfficer;
use App\Models\LegalSearchOnlinePayment;
use App\Models\LegalSearchOnlineRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Drives the Online Legal Search request → approval → email workflow.
 *
 * The public portal used to render the full report as soon as a payment
 * cleared. It now opens a request instead: Directors / Deputy Directors are
 * notified, and only on their approval is the report emailed out as a PDF.
 */
class LegalSearchApprovalService
{
    public function __construct(
        protected LegalSearchService $searchService,
        protected UserNotificationService $notifier,
    ) {
    }

    // ── Approver resolution ──────────────────────────────────────────

    /**
     * Staff who may review Online Legal Search requests: anyone whose
     * `users.rank` matches the configured Director / Deputy Director ranks,
     * plus super admins when allowed.
     */
    public function approvers(): Collection
    {
        $cfg = config('legal_search.online_approval');

        $users = User::query()
            ->where('is_active', 1)
            ->where(function ($q) use ($cfg) {
                foreach ((array) ($cfg['approver_ranks'] ?? []) as $rank) {
                    $q->orWhere('rank', $rank);
                }
                foreach ((array) ($cfg['approver_rank_prefixes'] ?? []) as $prefix) {
                    $q->orWhere('rank', 'like', $prefix . '%');
                }
                if (!empty($cfg['allow_super_admin'])) {
                    $q->orWhere('assign_role', 'Supper Admin');
                }
            })
            ->get();

        $excluded = array_map('strtolower', (array) ($cfg['excluded_ranks'] ?? []));

        return $users->reject(
            fn (User $u) => in_array(strtolower((string) $u->rank), $excluded, true)
        )->values();
    }

    /**
     * May this user act on the approval queue?
     */
    public function isApprover(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $cfg = config('legal_search.online_approval');

        if (!empty($cfg['allow_super_admin']) && $user->assign_role === 'Supper Admin') {
            return true;
        }

        $rank = trim((string) $user->rank);
        if ($rank === '') {
            return false;
        }

        if (in_array(strtolower($rank), array_map('strtolower', (array) ($cfg['excluded_ranks'] ?? [])), true)) {
            return false;
        }

        foreach ((array) ($cfg['approver_ranks'] ?? []) as $allowed) {
            if (strcasecmp($rank, $allowed) === 0) {
                return true;
            }
        }

        foreach ((array) ($cfg['approver_rank_prefixes'] ?? []) as $prefix) {
            if (stripos($rank, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    // ── Request lifecycle ────────────────────────────────────────────

    /**
     * Open a request off a verified payment and alert the approvers.
     * Idempotent: a payment only ever opens one request.
     */
    public function openRequest(LegalSearchOnlinePayment $payment, array $context = []): LegalSearchOnlineRequest
    {
        $existing = LegalSearchOnlineRequest::where('payment_id', $payment->id)->first();
        if ($existing) {
            return $existing;
        }

        $request = LegalSearchOnlineRequest::create([
            'payment_id'      => $payment->id,
            'reference'       => $payment->reference,
            'tracking_id'     => $payment->tracking_id,
            'requester_email' => $payment->email,
            'requester_name'  => $context['name'] ?? null,
            'requester_phone' => $context['phone'] ?? null,
            'file_number'     => $payment->file_number,
            'search_params'   => $payment->search_params,
            'ip_address'      => $context['ip'] ?? null,
            'status'          => LegalSearchOnlineRequest::STATUS_PENDING,
            'submitted_at'    => now(),
        ]);

        $request->assignRequestNo();

        $this->notifyApprovers($request);
        $this->acknowledgeRequester($request);

        return $request;
    }

    /**
     * In-app notification (bell) + email to every Director / Deputy Director.
     * Best-effort: a notification failure must never lose the request.
     */
    protected function notifyApprovers(LegalSearchOnlineRequest $request): void
    {
        $approvers = $this->approvers();

        foreach ($approvers as $approver) {
            try {
                $this->notifier->create(
                    (int) $approver->id,
                    'legal_search_online_request',
                    'Online Legal Search request awaiting approval',
                    sprintf(
                        '%s requested a Legal Search on file %s. Review and approve to release the report.',
                        $request->requester_email,
                        $request->file_number ?: '—'
                    ),
                    [
                        'request_id'  => $request->id,
                        'request_no'  => $request->request_no,
                        'file_number' => $request->file_number,
                        'url'         => route('legal-search-online.admin.requests', ['highlight' => $request->id]),
                    ],
                    ['module' => 'legal_search_online']
                );
            } catch (\Throwable $e) {
                Log::warning('LegalSearchApprovalService: in-app notification failed', [
                    'request_id' => $request->id,
                    'user_id'    => $approver->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            $email = trim((string) $approver->email);
            if ($email === '') {
                continue;
            }

            try {
                Mail::to($email)->send(new LegalSearchRequestPendingApproval($request, $approver));
            } catch (\Throwable $e) {
                Log::warning('LegalSearchApprovalService: approver email failed', [
                    'request_id' => $request->id,
                    'email'      => $email,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        if ($approvers->isEmpty()) {
            Log::warning('LegalSearchApprovalService: no approvers configured — request will sit unreviewed', [
                'request_id' => $request->id,
            ]);
        }
    }

    /**
     * Tell the requester their request is in the queue.
     */
    protected function acknowledgeRequester(LegalSearchOnlineRequest $request): void
    {
        try {
            Mail::to($request->requester_email)->send(new LegalSearchRequestSubmitted($request));
        } catch (\Throwable $e) {
            Log::warning('LegalSearchApprovalService: acknowledgement email failed', [
                'request_id' => $request->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the report payload for a request using the canonical print engine.
     */
    public function buildReport(LegalSearchOnlineRequest $request): ?array
    {
        $fileNumber = trim((string) $request->file_number);
        if ($fileNumber === '') {
            return null;
        }

        $built = $this->searchService->buildPrintReport(['file_number' => $fileNumber]);

        return $built['payload']['data'] ?? null;
    }

    /**
     * Render the report exactly as the requester receives it.
     *
     * Single source of truth for the emailed attachment and the approver's
     * preview, so what is reviewed is byte-for-byte what is delivered.
     */
    public function renderPdf(LegalSearchOnlineRequest $request, array $report): \Barryvdh\DomPDF\PDF
    {
        return \Barryvdh\DomPDF\Facade\Pdf::loadView('online_legal_search.print.report_pdf', [
            'report'        => $report,
            'searchRequest' => $request,
            // Must be an inline data URI — DomPDF will not fetch over HTTP, so an
            // unreadable signature has to render as a blank line, never a broken one.
            'signature'     => \App\Support\SignatureImage::embeddable($request->reviewer_signature_path),
        ])->setPaper('a4', 'landscape');
    }

    /**
     * The signing user's own signature, ready to preview in the approve dialog.
     *
     * @return array{has_signature: bool, data_uri: ?string}
     */
    public function signatureFor(?User $user): array
    {
        $officer = $this->signingOfficerFor($user);

        // Digital Signature Control (signing_officers) is the authority for a
        // staff signature; users.signature is only a legacy fallback. This is
        // the same precedence the Digital Signature Control listing uses.
        $path = trim((string) ($officer->signature_file ?? '')) ?: trim((string) ($user->signature ?? ''));

        // Deliberately the strict check: if the file cannot be read and inlined
        // it will not appear on the issued PDF either, so the approver must be
        // told it is unusable rather than shown a preview the report will not
        // reproduce.
        $dataUri = \App\Support\SignatureImage::embeddable($path);

        return [
            'has_signature' => $dataUri !== null,
            'data_uri'      => $dataUri,
            'path'          => $dataUri !== null ? $path : null,
            // Distinguishes "never uploaded one" from "uploaded, file missing".
            'unreadable'    => $path !== '' && $dataUri === null,
            'registered'    => $officer !== null,
            // Second-level auth required before the signature may be applied.
            'method'        => $officer->notification_type ?? null,
            'name'          => $officer->name ?? ($user->name ?? $user->username ?? null),
            'rank'          => ($user->rank ?? null) ?: ($officer->rank ?? null),
        ];
    }

    /**
     * The Digital Signature Control record for a staff user, if registered.
     */
    public function signingOfficerFor(?User $user): ?LandOfficer
    {
        if (!$user) {
            return null;
        }

        return LandOfficer::where('user_id', $user->id)->first();
    }

    /**
     * Session key holding the moment this user last cleared second-level auth.
     */
    protected function signatureSessionKey(User $user): string
    {
        return 'ols_signature_verified_at_' . $user->id;
    }

    /**
     * Record that the approver has just cleared their verification method.
     */
    public function markSignatureVerified(User $user): void
    {
        session([$this->signatureSessionKey($user) => now()->toIso8601String()]);
    }

    /**
     * Has this user cleared second-level auth recently enough to sign?
     *
     * The dialog can set the "apply signature" flag on its own, so the server
     * re-checks this before stamping a signature onto an issued report.
     */
    public function signatureVerified(User $user, int $withinMinutes = 15): bool
    {
        $at = session($this->signatureSessionKey($user));

        if (!$at) {
            return false;
        }

        try {
            return \Carbon\Carbon::parse($at)->greaterThan(now()->subMinutes($withinMinutes));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Clear the verification, so each signature is separately authorised.
     */
    public function forgetSignatureVerification(User $user): void
    {
        session()->forget($this->signatureSessionKey($user));
    }

    /**
     * File name used for the attachment and the preview tab.
     */
    public function pdfFileName(LegalSearchOnlineRequest $request): string
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', (string) ($request->file_number ?: 'report'));
        $slug = trim((string) $slug, '-') ?: 'report';

        return 'Legal-Search-Report-' . $slug . '.pdf';
    }

    /**
     * Approve a request and email the report to the requester as a PDF.
     * Returns true when the email went out.
     *
     * @throws \RuntimeException when the report cannot be generated.
     */
    public function approve(LegalSearchOnlineRequest $request, User $approver, ?string $note = null, bool $sign = false): bool
    {
        $report = $this->buildReport($request);

        if (!$report) {
            throw new \RuntimeException(
                'The report for file ' . ($request->file_number ?: '—') . ' could not be generated, so nothing was sent. Nothing has been changed.'
            );
        }

        // Signing is opt-in: the approver presses "Sign" in the approve dialog
        // and clears their Digital Signature Control verification method.
        // Without it the report goes out with a blank signature line.
        $signaturePath = $sign ? (string) ($this->signatureFor($approver)['path'] ?? '') : '';

        $request->forceFill([
            'status'                  => LegalSearchOnlineRequest::STATUS_APPROVED,
            'reviewed_by'             => $approver->id,
            'reviewer_name'           => $approver->name ?: $approver->username,
            'reviewer_rank'           => $approver->rank,
            'reviewer_signature_path' => $signaturePath ?: null,
            'signed_at'               => $signaturePath !== '' ? now() : null,
            'reviewed_at'             => now(),
            'review_note'             => $note ?: null,
        ])->save();

        try {
            Mail::to($request->requester_email)->send(
                new LegalSearchRequestApproved($request, $report)
            );

            $request->forceFill(['emailed_at' => now(), 'email_error' => null])->save();

            return true;
        } catch (\Throwable $e) {
            // The approval stands; only delivery failed. Record why so staff can
            // retry from the queue rather than re-approving.
            $request->forceFill(['email_error' => $e->getMessage()])->save();

            Log::error('LegalSearchApprovalService: report email failed', [
                'request_id' => $request->id,
                'error'      => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Re-send the report for an already-approved request.
     */
    public function resend(LegalSearchOnlineRequest $request): bool
    {
        $report = $this->buildReport($request);

        if (!$report) {
            throw new \RuntimeException('The report could not be generated, so nothing was sent.');
        }

        try {
            Mail::to($request->requester_email)->send(
                new LegalSearchRequestApproved($request, $report)
            );

            $request->forceFill(['emailed_at' => now(), 'email_error' => null])->save();

            return true;
        } catch (\Throwable $e) {
            $request->forceFill(['email_error' => $e->getMessage()])->save();

            return false;
        }
    }

    /**
     * Reject a request and tell the requester why.
     */
    public function reject(LegalSearchOnlineRequest $request, User $approver, string $reason): void
    {
        $request->forceFill([
            'status'           => LegalSearchOnlineRequest::STATUS_REJECTED,
            'reviewed_by'      => $approver->id,
            'reviewer_name'    => $approver->name ?: $approver->username,
            'reviewer_rank'    => $approver->rank,
            'reviewed_at'      => now(),
            'rejection_reason' => $reason,
        ])->save();

        try {
            Mail::to($request->requester_email)->send(new LegalSearchRequestRejected($request));
        } catch (\Throwable $e) {
            $request->forceFill(['email_error' => $e->getMessage()])->save();

            Log::warning('LegalSearchApprovalService: rejection email failed', [
                'request_id' => $request->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
