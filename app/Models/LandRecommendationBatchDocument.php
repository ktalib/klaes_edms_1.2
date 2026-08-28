<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * The mother's scanned recommendation letter for a subdivision batch.
 *
 * A subdivision's children inherit the mother's recommendation instead of earning
 * one each, so no letter is printed for a child. The mother's signed letter is
 * scanned once, hung off the batch, and every child in that batch views this one
 * document.
 *
 * One row per rofo_batch_id — see the unique index. A re-upload replaces the row
 * (and the file behind it) rather than adding a second.
 *
 * @see \App\Http\Controllers\LandRecommendationBatchDocumentController
 */
class LandRecommendationBatchDocument extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'land_recommendation_batch_documents';

    /** Where the scans live on the 'public' disk. */
    public const DIRECTORY = 'land_recommendations/batch_documents';

    protected $fillable = [
        'rofo_batch_id',
        'mother_file_no',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'size_bytes'  => 'integer',
    ];

    /**
     * Browser-facing URL for the stored scan, through the public/storage symlink.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /**
     * Does the viewer's browser render this inline, or does it need a download?
     * Images and PDFs open in a tab; anything else is handed over as a file.
     */
    public function isViewableInline(): bool
    {
        $mime = strtolower((string) $this->mime_type);

        return str_starts_with($mime, 'image/') || $mime === 'application/pdf';
    }

    /**
     * What the file is, for a label: "JPG · 1.2 MB".
     */
    public function summary(): string
    {
        $extension = strtoupper(pathinfo((string) $this->path, PATHINFO_EXTENSION));
        $bytes = (int) $this->size_bytes;

        if ($bytes <= 0) {
            return $extension;
        }

        $size = $bytes >= 1048576
            ? round($bytes / 1048576, 1) . ' MB'
            : max(1, (int) round($bytes / 1024)) . ' KB';

        return trim($extension . ' · ' . $size, ' ·');
    }
}
