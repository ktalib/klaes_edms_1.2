<?php

namespace App\Http\Controllers\OnlineLegalSearch;

use App\Http\Controllers\Controller;
use App\Http\Requests\OnlineLegalSearch\StoreIdVerificationRequest;
use App\Models\LegalSearchOnlineVerification;
use App\Services\IdNameVerificationService;
use App\Services\LegalSearchApprovalService;
use App\Services\Ocr\OcrException;
use App\Services\Ocr\OcrImagePreprocessor;
use App\Services\Ocr\OcrReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ID NAME verification for the public Online Legal Search payment card.
 *
 * WHAT THIS ESTABLISHES: that the full name the applicant typed also appears in
 * the text OCR read off the identification they uploaded. It is NOT proof that
 * the document is genuine, that it is unaltered, that the uploader is its
 * rightful holder, or that the ID number is valid with the issuing authority.
 * Nothing here or downstream may present it as identity verification.
 *
 * Order of operations, enforced server-side:
 *   validate -> store privately -> OCR -> compare -> persist -> gate payment.
 * The Paystack checkout is opened by the browser (Paystack Inline), so the gate
 * is a session-bound verified row that verifyPayment() re-checks; a browser that
 * skips this endpoint cannot manufacture one.
 */
class OnlineLsIdVerificationController extends Controller
{
    public function __construct(
        private readonly IdNameVerificationService $nameVerification,
        private readonly OcrReader $ocr,
        private readonly OcrImagePreprocessor $preprocessor,
    ) {
    }

    /**
     * Receive the applicant's identification, verify the name, and decide whether
     * the checkout may open.
     */
    public function store(StoreIdVerificationRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Re-submitting the same identification for the same search must not pile
        // up rows (or duplicate applicant records). The session already holding a
        // verification for this file number is reused and overwritten in place.
        $verification = $this->existingForSession($data['file_number']);

        $frontPath = null;

        try {
            $frontPath = $this->storePrivately($request->file('id_front'), 'front');
        } catch (\Throwable $e) {
            $this->deleteQuietly([$frontPath]);

            Log::error('Online LS identification upload failed', [
                'file_number' => $data['file_number'],
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'We could not save your identification. Please try again.',
            ], 500);
        }

        // --- OCR -------------------------------------------------------------
        // An engine fault must never take down the payment page. It is logged in
        // full for us and reported to the applicant as a temporary outage — NOT as
        // an unreadable document, which would send them into a pointless loop
        // re-photographing a perfectly good ID while the real fault is server-side
        // (a missing tesseract binary being the usual cause).
        //
        // The row is left `pending` rather than `failed`: no comparison ever ran,
        // so recording a name mismatch against this applicant would be untrue.
        try {
            $extractedText = $this->ocrOf($frontPath);
        } catch (OcrException $e) {
            Log::error('Online LS ID verification OCR failed', [
                'file_number' => $data['file_number'],
                'error'       => $e->getMessage(),
                'previous'    => $e->getPrevious()?->getMessage(),
            ]);

            $this->persist($verification, $data, $frontPath, [
                'status' => LegalSearchOnlineVerification::STATUS_PENDING,
                'score'  => 0,
                'extracted_text' => '',
            ], $request);

            return $this->outcome(
                LegalSearchOnlineVerification::STATUS_PENDING,
                0,
                (string) config('id_verification.messages.unavailable')
            );
        }

        // The engine ran but found nothing legible. Distinct from the fault above:
        // this one the applicant can fix with a better photo.
        if (trim($extractedText) === '') {
            $this->persist($verification, $data, $frontPath, [
                'status' => LegalSearchOnlineVerification::STATUS_FAILED,
                'score'  => 0,
                'extracted_text' => '',
            ], $request);

            return $this->outcome(
                LegalSearchOnlineVerification::STATUS_FAILED,
                0,
                (string) config('id_verification.messages.unreadable')
            );
        }

        $result = $this->nameVerification->compare($data['applicant_full_name'], $extractedText);

        $this->persist($verification, $data, $frontPath, $result, $request);

        return $this->outcome(
            $result['status'],
            $result['score'],
            (string) config('id_verification.messages.' . $result['status'], config('id_verification.messages.failed'))
        );
    }

    /**
     * Stream a stored identification image to an authorized reviewer.
     *
     * Gated on the same Director / Deputy Director check that guards the rest of
     * the Online Legal Search approval queue. The path is never in the HTML and
     * never in JavaScript — this route addresses the row and the side, so one
     * applicant's document cannot be reached from another applicant's id, and a
     * guessed storage path leads nowhere because the disk is outside /storage.
     */
    public function document(
        Request $request,
        int $id,
        string $side,
        LegalSearchApprovalService $approvalService
    ): StreamedResponse {
        abort_unless(
            $approvalService->isApprover($request->user()),
            403,
            'Only a Director or Deputy Director may view submitted identification.'
        );

        // 'back' is still accepted: rows captured before the back image was
        // dropped have one on file, and a reviewer must still be able to open it.
        abort_unless(in_array($side, ['front', 'back'], true), 404);

        $verification = LegalSearchOnlineVerification::findOrFail($id);
        $path = $side === 'front' ? $verification->id_front_path : $verification->id_back_path;

        abort_if(empty($path), 404, 'No document was uploaded for this side.');

        $disk = Storage::disk($this->disk());
        abort_unless($disk->exists($path), 404, 'The stored document is no longer available.');

        // Inline, never as a download with the applicant's own filename.
        return $disk->response($path, 'identification-' . $verification->id . '-' . $side, [
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The verification this browser session already holds for a file number, if
     * any. Session-bound so a token cannot be lifted from one applicant to
     * another, and reused so a resubmission updates rather than duplicates.
     */
    private function existingForSession(string $fileNumber): ?LegalSearchOnlineVerification
    {
        $token = session('ols_verification_token');

        if (!$token) {
            return null;
        }

        return LegalSearchOnlineVerification::where('session_token', $token)
            ->where('file_number', $fileNumber)
            ->first();
    }

    /**
     * OCR one stored image, working on a preprocessed temporary copy.
     *
     * The temporary file is deleted in `finally`, so it goes away on an OCR
     * exception exactly as it does on success. The stored original is untouched.
     */
    private function ocrOf(string $storedPath): string
    {
        $disk = Storage::disk($this->disk());
        $absolute = $disk->path($storedPath);
        $temporary = null;

        try {
            $temporary = $this->preprocessor->prepare($absolute);

            return $this->ocr->text($temporary ?: $absolute);
        } finally {
            $this->preprocessor->discard($temporary);
        }
    }

    /**
     * Save the ID image on the private disk under a generated name.
     *
     * The applicant's own filename is never used: it is attacker-controlled text
     * that would end up on the filesystem. The extension is taken from the
     * validated image type, not from the submitted name.
     */
    private function storePrivately(UploadedFile $file, string $side): string
    {
        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $name = sprintf('%s_%s_%s.%s', $side, now()->format('Ymd_His'), Str::random(24), $extension);

        $directory = trim((string) config('id_verification.uploads.directory'), '/')
            . '/' . now()->format('Y/m');

        $path = $file->storeAs($directory, $name, $this->disk());

        if (!$path) {
            throw new \RuntimeException('Private storage returned no path for the identification image.');
        }

        return $path;
    }

    /**
     * Write the verification row.
     *
     * The status and score written here are always the ones this server computed;
     * no value from the request is consulted. `id_verified_at` is stamped only on
     * a genuine pass, so a review or failed result can never read as verified.
     */
    private function persist(
        ?LegalSearchOnlineVerification $existing,
        array $data,
        string $frontPath,
        array $result,
        Request $request
    ): LegalSearchOnlineVerification {
        $verified = $result['status'] === LegalSearchOnlineVerification::STATUS_VERIFIED;

        // A fresh token per submission: the previous one stops being presentable
        // the moment the applicant re-verifies.
        $token = (string) Str::uuid();

        $attributes = [
            'file_number'               => $data['file_number'],
            'requester_email'           => $data['email'],
            'session_token'             => $token,
            'applicant_full_name'       => $data['applicant_full_name'],
            'applicant_phone'           => $data['applicant_phone'],
            'applicant_address'         => $data['applicant_address'],
            'identification_type'       => $data['identification_type'],
            'identification_type_other' => $data['identification_type_other'] ?? null,
            'id_front_path'             => $frontPath,
            'id_ocr_text'               => config('id_verification.store_raw_text')
                ? ($result['extracted_text'] ?? null)
                : null,
            'id_name_match_score'       => $result['score'] ?? 0,
            'id_verification_status'    => $result['status'],
            'id_verified_at'            => $verified ? now() : null,
            'ip_address'                => $request->ip(),
        ];

        if ($existing) {
            // Replacing a previous attempt: drop its images so rejected uploads do
            // not accumulate on disk. id_back_path is included for rows written
            // before the back image was dropped - they still have a file to clean up.
            $this->deleteQuietly([
                $existing->id_front_path !== $frontPath ? $existing->id_front_path : null,
                $existing->id_back_path,
            ]);

            $existing->fill($attributes)->save();
            $verification = $existing;
        } else {
            $verification = LegalSearchOnlineVerification::create($attributes);
        }

        session(['ols_verification_token' => $token]);

        return $verification;
    }

    /**
     * The applicant-facing response.
     *
     * Carries the status, the score, and one of the fixed messages — never the
     * OCR transcript, the matched parts, the storage paths, or exception detail.
     */
    private function outcome(string $status, int $score, string $message): JsonResponse
    {
        return response()->json([
            'success'   => $status === LegalSearchOnlineVerification::STATUS_VERIFIED,
            'status'    => $status,
            'score'     => $score,
            'message'   => $message,
            'can_pay'   => $status === LegalSearchOnlineVerification::STATUS_VERIFIED,
        ], 200);
    }

    /** @param array<int, string|null> $paths */
    private function deleteQuietly(array $paths): void
    {
        $disk = Storage::disk($this->disk());

        foreach (array_filter($paths) as $path) {
            try {
                $disk->delete($path);
            } catch (\Throwable $e) {
                Log::warning('Could not delete a superseded identification image', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function disk(): string
    {
        return (string) config('id_verification.uploads.disk', 'ols_private');
    }
}
