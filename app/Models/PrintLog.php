<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintLog extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'print_logs';

    protected $fillable = [
        'reference_number',
        'document_type',
        'print_type',
        'status',
        'user_id'
    ];

    /**
     * Get the user who printed the document.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A reset marker, not a print: written when a Super Admin puts a document's
     * print state back so it can be printed again (LandRofoController::resetPrint).
     *
     * `status` holds the scope — 'all', 'original' or 'office' — which says which
     * copies the reset covered. Nothing else in the app writes this type, and no
     * print_logs row is ever deleted, so the history of what went on paper stays
     * whole while "is this printed?" means "printed since the last reset".
     */
    public const TYPE_RESET = 'PrintReset';

    /** Which copies each reset scope reopens. */
    public const RESET_SCOPES = [
        'all'      => ['Original', 'Duplicate', 'Triplicate'],
        'original' => ['Original'],
        'office'   => ['Duplicate', 'Triplicate'],
    ];

    /**
     * The moment after which a print of $copy counts, for one document. Null when
     * that copy has never been reset.
     */
    public static function resetCutoff($logs, string $copy)
    {
        $cutoff = null;

        foreach ($logs as $log) {
            if ($log->print_type !== self::TYPE_RESET) {
                continue;
            }

            $covers = self::RESET_SCOPES[strtolower(trim((string) $log->status))] ?? [];
            if (!in_array($copy, $covers, true)) {
                continue;
            }

            if ($cutoff === null || $log->created_at > $cutoff) {
                $cutoff = $log->created_at;
            }
        }

        return $cutoff;
    }

    /** How many times $copy has been printed since it was last reset. */
    public static function countSinceReset($logs, string $copy): int
    {
        $cutoff = self::resetCutoff($logs, $copy);

        return $logs->filter(function ($log) use ($copy, $cutoff) {
            if ($log->status !== $copy || $log->print_type === self::TYPE_RESET) {
                return false;
            }
            return $cutoff === null || $log->created_at > $cutoff;
        })->count();
    }

    /**
     * Reference numbers of $documentType printed under $printType and NOT reset
     * since — upper-cased and trimmed, ready to match against a file number.
     */
    public static function printedSinceReset(string $documentType, string $printType): array
    {
        $logs = static::where('document_type', $documentType)
            ->whereIn('print_type', [$printType, self::TYPE_RESET])
            ->get(['reference_number', 'print_type', 'status', 'created_at']);

        $key = fn ($r) => strtoupper(trim((string) $r));

        return $logs->groupBy(fn ($log) => $key($log->reference_number))
            ->filter(function ($forOneFile) {
                // Printed if any copy still counts as printed after the resets.
                foreach (['Original', 'Duplicate', 'Triplicate'] as $copy) {
                    if (self::countSinceReset($forOneFile, $copy) > 0) {
                        return true;
                    }
                }

                // A run logged with no copy named at all (older rows) counts as a
                // print unless something has been reset since.
                $unnamed = $forOneFile->filter(fn ($l) => $l->print_type !== self::TYPE_RESET
                    && !in_array($l->status, ['Original', 'Duplicate', 'Triplicate'], true));
                if ($unnamed->isEmpty()) {
                    return false;
                }

                $lastReset = $forOneFile->where('print_type', self::TYPE_RESET)->max('created_at');

                return $lastReset === null || $unnamed->max('created_at') > $lastReset;
            })
            ->keys()
            ->all();
    }
}
