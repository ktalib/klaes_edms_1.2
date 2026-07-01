<?php

namespace App\Http\Controllers;

use App\Models\DuplicateFileno;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DuplicateCallupController extends Controller
{
    /**
     * Render a print-ready "Duplicate File Call-up" sheet that places the two
     * physical duplicate files side by side: the indexed file (file_indexings)
     * and the flagged duplicate entry (duplicate_fileno). Both are keyed by the
     * shared file number; optional indexed_id / duplicate_id target exact rows.
     *
     * GET /duplicate-callup?file_number=...&indexed_id=...&duplicate_id=...
     */
    public function sheet(Request $request): View
    {
        $fileNumber = trim((string) $request->query('file_number', ''));
        $indexedId = (int) $request->query('indexed_id', 0);
        $duplicateId = (int) $request->query('duplicate_id', 0);

        // File 1 — the indexed record.
        $indexedQuery = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->select('file_number', 'file_title', 'plot_number', 'location', 'created_at');

        if ($indexedId > 0) {
            $indexedQuery->where('id', $indexedId);
        } else {
            $indexedQuery->where('file_number', $fileNumber);
        }

        $indexed = $fileNumber === '' && $indexedId <= 0 ? null : $indexedQuery->first();

        // File 2 — the flagged duplicate entry.
        $duplicateQuery = DuplicateFileno::on('sqlsrv');

        if ($duplicateId > 0) {
            $duplicateQuery->where('id', $duplicateId);
        } else {
            $duplicateQuery->where('file_number', $fileNumber);
        }

        $duplicate = $fileNumber === '' && $duplicateId <= 0 ? null : $duplicateQuery->first();

        $file1 = $this->normalize($indexed);
        $file2 = $this->normalize($duplicate);

        // Prefer an actual matched file number for the header.
        $headerFileNumber = $fileNumber !== ''
            ? $fileNumber
            : ($file1['file_no'] !== '—' ? $file1['file_no'] : $file2['file_no']);

        return view('duplicate_callup.sheet', [
            'fileNumber' => $headerFileNumber,
            'generatedAt' => Carbon::now('Africa/Lagos')->format('Y-m-d H:i'),
            'file1' => $file1,
            'file2' => $file2,
        ]);
    }

    /**
     * Reduce an indexed/duplicate record to the five sheet fields, mapping
     * empties to an em dash and a missing record to "— not found —".
     *
     * @param  object|null  $record
     * @return array<string,string>
     */
    private function normalize($record): array
    {
        if (!$record) {
            return [
                'found' => false,
                'file_no' => '— not found —',
                'file_title' => '— not found —',
                'plot_number' => '— not found —',
                'location' => '— not found —',
                'date_indexed' => '— not found —',
            ];
        }

        $value = function ($raw) {
            $trimmed = trim((string) ($raw ?? ''));
            return $trimmed === '' ? '—' : $trimmed;
        };

        $date = '—';
        if (!empty($record->created_at)) {
            try {
                $date = Carbon::parse($record->created_at)->format('Y-m-d');
            } catch (\Throwable $e) {
                $date = (string) $record->created_at;
            }
        }

        return [
            'found' => true,
            'file_no' => $value($record->file_number ?? null),
            'file_title' => $value($record->file_title ?? null),
            'plot_number' => $value($record->plot_number ?? null),
            'location' => $value($record->location ?? null),
            'date_indexed' => $date,
        ];
    }
}
