<?php

namespace App\Http\Controllers;

use App\Models\LandRecommendation;
use App\Models\LandRecommendationBatchDocument;
use App\Support\LandRecommendationLog as RecLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * The mother's scanned recommendation letter for a subdivision batch.
 *
 * A subdivision is one grant split into plots, so its children inherit the
 * mother's recommendation instead of earning one each: the letter is printed once,
 * for the mother, and signed on paper. The mothers of these batches have no
 * recommendation record in KLAES — the letter exists only as that signed sheet —
 * so a child has nothing of its own to print and nothing to link to.
 *
 * That sheet is scanned once here and hung off the batch. Every child in the batch
 * then shows View in place of Print, opening this one document; there is no
 * per-child copy to keep in step, and no second upload to reconcile.
 *
 * Uploading again replaces the file and the row, and deletes the scan it replaced
 * — a batch has one current letter, and the old scan is not evidence of anything
 * once a corrected one is signed.
 */
class LandRecommendationBatchDocumentController extends Controller
{
    /** 20 MB. A phone photograph of an A4 letter is comfortably inside this. */
    private const MAX_KILOBYTES = 20480;

    /**
     * What a scanner or a phone actually produces. PDF is included because a
     * multi-page letter comes off a departmental scanner as one, and refusing it
     * would send the officer away to convert a file for no reason.
     */
    private const ACCEPTED = 'jpg,jpeg,png,webp,heic,pdf';

    /**
     * Store (or replace) the scan for one batch.
     */
    public function store(Request $request, string $batchId)
    {
        $request->validate([
            'document' => 'required|file|mimes:' . self::ACCEPTED . '|max:' . self::MAX_KILOBYTES,
        ], [
            'document.mimes' => 'Upload the letter as an image (JPG, PNG, WEBP, HEIC) or a PDF.',
            'document.max'   => 'That file is larger than 20 MB. Scan it at a lower resolution and try again.',
        ]);

        // The batch has to exist, and it has to be a subdivision: a regular batch is
        // an arbitrary set of unrelated files with no mother whose letter could
        // cover them, and attaching one would say something untrue about all of them.
        $batch = $this->batchOrNull($batchId);

        if (!$batch) {
            return $this->fail($request, 'That batch no longer exists.', 404);
        }

        if (trim((string) $batch->batch_mother_file_no) === '') {
            return $this->fail($request, 'Only a subdivision batch has a mother recommendation to upload.', 422);
        }

        $file = $request->file('document');

        // Named for the batch, so the file is identifiable on disk on its own — a
        // scan found in a backup should not need the database to say what it is.
        // The random tail is not decoration: a replace within the same second would
        // otherwise land on the timestamped name it is replacing, and the cleanup
        // below would be deleting the file it had just written.
        $name = 'mother-recommendation-' . $batchId . '-' . now()->format('YmdHis')
            . '-' . strtolower(substr(bin2hex(random_bytes(3)), 0, 6))
            . '.' . strtolower($file->getClientOriginalExtension() ?: 'jpg');

        $path = $file->storeAs(LandRecommendationBatchDocument::DIRECTORY, $name, 'public');

        if (!$path) {
            RecLog::error('Batch document upload failed to store', [
                'rofo_batch_id' => $batchId,
                'original_name' => $file->getClientOriginalName(),
            ]);

            return $this->fail($request, 'The file could not be saved. Try again.', 500);
        }

        $existing = LandRecommendationBatchDocument::where('rofo_batch_id', $batchId)->first();
        $replaced = $existing?->path;

        $document = LandRecommendationBatchDocument::updateOrCreate(
            ['rofo_batch_id' => $batchId],
            [
                'mother_file_no' => $batch->batch_mother_file_no,
                'path'           => $path,
                'original_name'  => $file->getClientOriginalName(),
                'mime_type'      => $file->getClientMimeType(),
                'size_bytes'     => $file->getSize(),
                'uploaded_by'    => Auth::id(),
                'uploaded_at'    => now(),
            ]
        );

        // Only once the new row is committed. Deleting first would leave the batch
        // with no letter at all if the write below it failed.
        if ($replaced && $replaced !== $path) {
            Storage::disk('public')->delete($replaced);
        }

        RecLog::info($replaced ? 'Mother recommendation replaced' : 'Mother recommendation uploaded', [
            'rofo_batch_id'  => $batchId,
            'mother_file_no' => $batch->batch_mother_file_no,
            'children'       => $batch->children,
            'original_name'  => $document->original_name,
            'size_bytes'     => $document->size_bytes,
            'replaced_path'  => $replaced,
        ]);

        return response()->json([
            'success'  => true,
            'message'  => $replaced
                ? 'The mother recommendation was replaced. All ' . $batch->children . ' children now show the new copy.'
                : 'Uploaded. All ' . $batch->children . ' children of this batch now show this recommendation.',
            'document' => $this->payload($document, $batchId),
        ]);
    }

    /**
     * Open the scan. Every child of the batch links here, so one route answers for
     * the whole batch and the storage path never reaches the page.
     */
    public function show(string $batchId)
    {
        $document = LandRecommendationBatchDocument::where('rofo_batch_id', $batchId)->first();

        if (!$document || !Storage::disk('public')->exists($document->path)) {
            abort(404, 'No mother recommendation has been uploaded for this batch.');
        }

        // Images and PDFs open in the tab the officer clicked from; anything else is
        // handed over as a download rather than dumped into the browser as bytes.
        return response()->file(
            Storage::disk('public')->path($document->path),
            $document->isViewableInline()
                ? ['Content-Disposition' => 'inline; filename="' . addslashes($document->original_name ?: basename($document->path)) . '"']
                : []
        );
    }

    /**
     * Remove the scan, putting the batch's children back to "Upload".
     */
    public function destroy(Request $request, string $batchId)
    {
        $document = LandRecommendationBatchDocument::where('rofo_batch_id', $batchId)->first();

        if (!$document) {
            return $this->fail($request, 'Nothing has been uploaded for this batch.', 404);
        }

        Storage::disk('public')->delete($document->path);
        $document->delete();

        RecLog::warning('Mother recommendation removed', [
            'rofo_batch_id' => $batchId,
            'path'          => $document->path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'The mother recommendation was removed. Upload a new one to restore it for the children.',
        ]);
    }

    /**
     * The batch as a row: its mother and how many children it carries. A batch is
     * not a table of its own — it is a rofo_batch_id shared by its children — so it
     * is summarised from them.
     */
    private function batchOrNull(string $batchId)
    {
        return LandRecommendation::query()
            ->where('rofo_batch_id', $batchId)
            ->selectRaw('MAX(batch_mother_file_no) AS batch_mother_file_no, COUNT(*) AS children')
            ->havingRaw('COUNT(*) > 0')
            ->first();
    }

    /**
     * What the page needs to swap a row from "Upload" to "View".
     */
    private function payload(LandRecommendationBatchDocument $document, string $batchId): array
    {
        return [
            'view_url'      => route('land-recommendations.batch-document.show', $batchId),
            'original_name' => $document->original_name,
            'summary'       => $document->summary(),
            'uploaded_at'   => optional($document->uploaded_at)->format('d/m/Y H:i'),
        ];
    }

    private function fail(Request $request, string $message, int $status)
    {
        RecLog::warning('Batch document request refused', [
            'message' => $message,
            'status'  => $status,
        ]);

        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
