<?php

namespace App\Http\Controllers\TitleStatus;

use App\Http\Controllers\Controller;
use App\Models\TitleStatusApplication;
use App\Services\TitleStatusService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TitleStatusController extends Controller
{
    public function __construct(
        protected TitleStatusService $titleStatusService
    ) {}

    public function index(Request $request): View
    {
        $url    = trim((string) $request->input('url', 'land'));
        $limit  = max(10, min((int) $request->input('limit', 50), 200));
        $search = trim((string) $request->input('search'));

        $records = TitleStatusApplication::query()
            ->where('url', $url)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('file_no', 'LIKE', "%{$search}%")
                        ->orWhere('applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('title_no', 'LIKE', "%{$search}%")
                        ->orWhere('title_type', 'LIKE', "%{$search}%")
                        ->orWhere('plot_no', 'LIKE', "%{$search}%")
                        ->orWhere('location', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($limit);

        $urlLabel             = $this->titleStatusService->urlLabel($url);
        $authorityOptions     = TitleStatusApplication::AUTHORITY_OPTIONS;
        $initiatedByOptions   = TitleStatusApplication::INITIATED_BY_OPTIONS;
        $initiatedByByType    = TitleStatusApplication::INITIATED_BY_BY_TYPE;

        return view('title_status.index', compact('records', 'limit', 'url', 'urlLabel', 'authorityOptions', 'initiatedByOptions', 'initiatedByByType'));
    }

    public function show(int $id): JsonResponse
    {
        $record = TitleStatusApplication::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $record,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['url']         = $request->input('url', 'land');
        $data['captured_by'] = Auth::id();
        $data['status']      = TitleStatusApplication::STATUS_PENDING;

        $record = TitleStatusApplication::create($data);

        // Flag source files and push to archive tables
        $this->titleStatusService->flagAndDecommission($record);

        return response()->json([
            'success' => true,
            'message' => 'Title Status application created successfully.',
            'data'    => $record,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = TitleStatusApplication::findOrFail($id);

        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data               = $validator->validated();
        $data['updated_by'] = Auth::id();

        $record->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Title Status application updated successfully.',
            'data'    => $record->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = TitleStatusApplication::findOrFail($id);

        if ($record->status === TitleStatusApplication::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Approved applications cannot be deleted.',
            ], 403);
        }

        $record->update([
            'is_deleted' => 1,
            'deleted_by' => Auth::id(),
            'deleted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Application deleted successfully.',
        ]);
    }

    public function approve(int $id): JsonResponse
    {
        $record = TitleStatusApplication::findOrFail($id);

        $record->update([
            'status'      => TitleStatusApplication::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'updated_by'  => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Application approved.']);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $record = TitleStatusApplication::findOrFail($id);

        $reason = trim((string) $request->input('reason', ''));

        $record->update([
            'status'     => TitleStatusApplication::STATUS_REJECTED,
            'remark'     => $reason ? "Rejected: {$reason}" : 'Rejected',
            'updated_by' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Application rejected.']);
    }

    public function fileInfo(Request $request): JsonResponse
    {
        $fileNo = trim((string) $request->input('file_no', ''));

        if (!$fileNo) {
            return response()->json(['success' => false, 'message' => 'File number required.'], 422);
        }

        $record = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('file_number', $fileNo)
            ->first();

        if (!$record) {
            // Try fileNumber table as fallback
            $fn = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->where('mlsfNo', $fileNo)
                ->first();

            if (!$fn) {
                return response()->json(['success' => false, 'message' => 'File not found.'], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'source_table'   => 'fileNumber',
                    'source_id'      => $fn->id ?? null,
                    'file_no'        => $fileNo,
                    'file_title'     => $fn->FileName ?? null,
                    'current_holder' => $fn->FileName ?? null,
                    'plot_no'        => $fn->PlotNo ?? null,
                    'location'       => $fn->Location ?? null,
                    'land_use'       => $fn->LandUse ?? null,
                    'district'       => $fn->District ?? null,
                    'lga'            => $fn->LGA ?? null,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'source_table'   => 'file_indexings',
                'source_id'      => $record->id ?? null,
                'file_no'        => $fileNo,
                'file_title'     => $record->file_title ?? null,
                'current_holder' => $record->current_holder ?? $record->original_holder ?? null,
                'plot_no'        => $record->plot_number ?? null,
                'location'       => $record->location ?? null,
                'land_use'       => $record->land_use_type ?? null,
                'district'       => $record->district ?? null,
                'lga'            => $record->lga ?? null,
                'plot_size'      => $record->plot_size ?? null,
                'serial_no'      => $record->serial_no ?? null,
            ],
        ]);
    }

    public function generateRemark(Request $request): JsonResponse
    {
        $titleType     = (string) $request->input('title_type', '');
        $initiatedBy   = (string) $request->input('initiated_by', '');
        $reason        = (string) $request->input('reason', '');
        $applicantName = (string) $request->input('applicant_name', '');
        $fileNo        = (string) $request->input('file_no', '');

        $remark = $this->titleStatusService->generateRemark($titleType, $initiatedBy, $reason, $applicantName, $fileNo);

        return response()->json(['success' => true, 'remark' => $remark]);
    }

    private function rules(): array
    {
        return [
            'title_type'          => 'required|string|max:100',
            'file_no'             => 'required|string|max:255',
            'file_title'          => 'nullable|string|max:500',
            'applicant_name'      => 'nullable|string|max:255',
            'source_table'        => 'nullable|string|max:100',
            'source_id'           => 'nullable|integer',
            'plot_no'             => 'nullable|string|max:100',
            'house_no'            => 'nullable|string|max:100',
            'street_name'         => 'nullable|string|max:255',
            'district'            => 'nullable|string|max:255',
            'lga'                 => 'nullable|string|max:255',
            'state'               => 'nullable|string|max:100',
            'location'            => 'nullable|string|max:2000',
            'land_use'            => 'nullable|string|max:255',
            'title_no'            => 'nullable|string|max:255',
            'date_of_issue'       => 'nullable|date',
            'date_of_expiry'      => 'nullable|date',
            'authority'           => 'nullable|string|max:255',
            'authority_reference' => 'nullable|string|max:500',
            'initiated_by'        => 'nullable|string|max:50',
            'reason'              => 'nullable|string',
            'remark'              => 'nullable|string',
        ];
    }
}
