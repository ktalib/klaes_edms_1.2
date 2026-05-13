<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\FileNumber;
use Carbon\Carbon;

class FileDecommissioningController extends Controller
{
    /**
     * Display the file decommissioning page
     */
    public function index()
    {
        $totalActiveFiles = FileNumber::active()
            ->where(function($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->count();

        $decommissionedFromFileNumber = FileNumber::decommissioned()
            ->where(function($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->count();

        $decommissionedFromArchive = \Illuminate\Support\Facades\DB::connection('sqlsrv')
            ->table('decommissioned_files')->count();

        $totalDecommissionedFiles = $decommissionedFromFileNumber + $decommissionedFromArchive;

        return view('file_decommissioning.index', compact('totalActiveFiles', 'totalDecommissionedFiles'));
    }

    /**
     * Display the decommissioned files list page
     */
    public function decommissionedIndex()
    {
        $totalDecommissionedFiles = FileNumber::decommissioned()
            ->where(function($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->count();

        $recentDecommissioned = FileNumber::decommissioned()
            ->where(function($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->where('decommissioning_date', '>=', now()->subDays(30))
            ->count();

        return view('file_decommissioning.decommissioned_list', compact('totalDecommissionedFiles', 'recentDecommissioned'));
    }

    /**
     * Get active files data for DataTables
     */
    public function getActiveFilesData(Request $request)
    {
        try {
            $draw = $request->input('draw');
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $searchValue = $request->input('search.value', '');

            // Base query for active files
            $baseQuery = FileNumber::active()
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                });

            // Get total count
            $totalRecords = $baseQuery->count();

            // Apply search if provided
            if (!empty($searchValue)) {
                $baseQuery->where(function($query) use ($searchValue) {
                    $query->where('kangisFileNo', 'like', "%{$searchValue}%")
                          ->orWhere('NewKANGISFileNo', 'like', "%{$searchValue}%")
                          ->orWhere('FileName', 'like', "%{$searchValue}%")
                          ->orWhere('mlsfNo', 'like', "%{$searchValue}%");
                });
            }

            // Get filtered count
            $filteredRecords = $baseQuery->count();

            // Get the actual data with ordering and pagination
            $data = $baseQuery->select([
                    'id',
                    'kangisFileNo',
                    'mlsfNo',
                    'NewKANGISFileNo', 
                    'FileName',
                    'type',
                    'created_at'
                ])
                ->orderBy('id', 'desc')
                ->skip($start)
                ->take($length)
                ->get();

            // Format the data
            $formattedData = $data->map(function($row) {
                return [
                    'id' => $row->id,
                    'mlsfNo' => trim($row->mlsfNo ?? '') ?: 'N/A',
                    'kangisFileNo' => trim($row->kangisFileNo ?? '') ?: 'N/A',
                    'NewKANGISFileNo' => trim($row->NewKANGISFileNo ?? '') ?: 'N/A',
                    'FileName' => trim($row->FileName ?? '') ?: 'N/A',
                    'type' => trim($row->type ?? '') ?: 'N/A',
                    'commissioning_date' => 'N/A', // Not available in current structure
                    'created_at' => $row->created_at ? $row->created_at : 'N/A',
                    'action' => '<div class="flex justify-center space-x-2">
                        <button onclick="decommissionFile(' . $row->id . ')" 
                                class="bg-red-600 hover:bg-red-700 text-white text-sm px-3 py-1 rounded" title="Decommission File">
                            <i data-lucide="archive" class="w-4 h-4 inline mr-1"></i>Decommission
                        </button>
                    </div>'
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $formattedData
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in FileDecommissioningController getActiveFilesData: ' . $e->getMessage());
            
            return response()->json([
                'draw' => intval($request->input('draw')),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Error loading data: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get decommissioned files data for DataTables
     */
    public function getDecommissionedFilesData(Request $request)
    {
        try {
            $draw = $request->input('draw');
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $searchValue = $request->input('search.value', '');

            $conn = \Illuminate\Support\Facades\DB::connection('sqlsrv');

            // Query 1: From the decommissioned_files archive table (hard-deleted via PlotWorkflowService)
            $archiveQuery = $conn->table('decommissioned_files')
                ->select([
                    'id',
                    'mls_file_no as mlsfNo',
                    'kangis_file_no as kangisFileNo',
                    'new_kangis_file_no as NewKANGISFileNo',
                    'file_name as FileName',
                    'commissioning_date',
                    'decommissioning_date',
                    'decommissioning_reason',
                    'decommissioned_by',
                    'created_at',
                    \Illuminate\Support\Facades\DB::raw("'archive' as _source"),
                ]);

            // Query 2: From the fileNumber table (soft-decommissioned via is_decommissioned flag)
            $fileNumberQuery = $conn->table('fileNumber')
                ->where('is_decommissioned', 1)
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->select([
                    'id',
                    'mlsfNo',
                    'kangisFileNo',
                    'NewKANGISFileNo',
                    'FileName',
                    'commissioning_date',
                    'decommissioning_date',
                    'decommissioning_reason',
                    \Illuminate\Support\Facades\DB::raw("COALESCE(updated_by, created_by, 'System') as decommissioned_by"),
                    'created_at',
                    \Illuminate\Support\Facades\DB::raw("'filenumber' as _source"),
                ]);

            // UNION both sources
            $unionQuery = $archiveQuery->unionAll($fileNumberQuery);

            // Wrap in a subquery for counting/searching/pagination
            $baseQuery = $conn->table(\Illuminate\Support\Facades\DB::raw("({$unionQuery->toSql()}) as combined"))
                ->mergeBindings($unionQuery);

            // Total count (before search)
            $totalRecords = $baseQuery->count();

            // Apply search
            if (!empty($searchValue)) {
                $baseQuery->where(function ($query) use ($searchValue) {
                    $query->where('mlsfNo', 'like', "%{$searchValue}%")
                        ->orWhere('kangisFileNo', 'like', "%{$searchValue}%")
                        ->orWhere('NewKANGISFileNo', 'like', "%{$searchValue}%")
                        ->orWhere('FileName', 'like', "%{$searchValue}%")
                        ->orWhere('decommissioning_reason', 'like', "%{$searchValue}%")
                        ->orWhere('decommissioned_by', 'like', "%{$searchValue}%");
                });
            }

            $filteredRecords = $baseQuery->count();

            // Get data with pagination, ordered by decommissioning_date desc
            $data = $baseQuery
                ->orderByDesc('decommissioning_date')
                ->skip($start)
                ->take($length)
                ->get();

            // Format for DataTables
            $formattedData = $data->map(function ($row) {
                return [
                    'id' => $row->id,
                    'mls_file_no' => trim($row->mlsfNo ?? '') ?: 'N/A',
                    'kangis_file_no' => trim($row->kangisFileNo ?? '') ?: 'N/A',
                    'file_name' => trim($row->FileName ?? '') ?: 'N/A',
                    'commissioning_date' => $row->commissioning_date ? Carbon::parse($row->commissioning_date)->format('d M Y, h:i A') : 'N/A',
                    'decommissioning_date' => $row->decommissioning_date ? Carbon::parse($row->decommissioning_date)->format('d M Y, h:i A') : 'N/A',
                    'decommissioned_by' => trim($row->decommissioned_by ?? '') ?: 'System',
                    'decommissioning_reason' => strlen((string) ($row->decommissioning_reason ?? '')) > 50
                        ? substr($row->decommissioning_reason, 0, 50) . '...'
                        : ($row->decommissioning_reason ?? 'N/A'),
                    'action' => '<div class="flex justify-center space-x-2">
                        <button onclick="viewDecommissionedFile(' . $row->id . ', \'' . ($row->_source ?? 'archive') . '\')" 
                                class="text-blue-600 hover:text-blue-800 text-sm px-2 py-1 rounded hover:bg-blue-50" title="View Details">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </button>
                    </div>'
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $formattedData
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in FileDecommissioningController getDecommissionedFilesData: ' . $e->getMessage());

            return response()->json([
                'draw' => intval($request->input('draw')),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Error loading data: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get file details for decommissioning
     */
    public function getFileDetails($id)
    {
        try {
            $file = FileNumber::where('id', $id)
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->first();

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            // Check if file is already decommissioned
            if ($file->isDecommissioned()) {
                return response()->json([
                    'success' => false,
                    'message' => 'File is already decommissioned'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $file->id,
                    'mlsfNo' => $file->mlsfNo,
                    'kangisFileNo' => $file->kangisFileNo,
                    'NewKANGISFileNo' => $file->NewKANGISFileNo,
                    'FileName' => $file->FileName,
                    'type' => $file->type,
                    'commissioning_date' => null, // Not available in current structure
                    'created_at' => $file->created_at ? $file->created_at : null
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving file details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Decommission a file
     */
    public function decommissionFile(Request $request)
    {
        // Log the incoming request data for debugging
        \Log::info('Decommission request data:', $request->all());

        $validator = Validator::make($request->all(), [
            'file_id' => 'required|integer',
            'commissioning_date' => 'nullable|date',
            'decommissioning_date' => 'required|date',
            'decommissioning_reason' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $validator->errors()->all()),
                'errors' => $validator->errors(),
                'request_data' => $request->all() // Include request data for debugging
            ], 422);
        }

        try {
            $file = FileNumber::where('id', $request->file_id)
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->first();

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            // Check if file is already decommissioned
            if ($file->isDecommissioned()) {
                return response()->json([
                    'success' => false,
                    'message' => 'File is already decommissioned'
                ], 404);
            }

            // Decommission the file using the model method
            $commissioningDate = $request->commissioning_date ? Carbon::parse($request->commissioning_date) : null;
            $decommissioningDate = Carbon::parse($request->decommissioning_date);
            
            $file->decommission(
                $request->decommissioning_reason,
                $decommissioningDate,
                $commissioningDate
            );

            return response()->json([
                'success' => true,
                'message' => 'File decommissioned successfully',
                'data' => [
                    'file_id' => $file->id,
                    'mlsfNo' => $file->mlsfNo,
                    'decommissioning_date' => $request->decommissioning_date,
                    'decommissioning_reason' => $request->decommissioning_reason
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error decommissioning file: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error decommissioning file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get decommissioned file details
     */
    public function getDecommissionedFileDetails($id)
    {
        try {
            $decommissionedFile = FileNumber::decommissioned()
                ->where('id', $id)
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->first();

            if (!$decommissionedFile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Decommissioned file record not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $decommissionedFile->id,
                    'file_number_id' => $decommissionedFile->id,
                    'mls_file_no' => $decommissionedFile->mlsfNo,
                    'kangis_file_no' => $decommissionedFile->kangisFileNo,
                    'new_kangis_file_no' => $decommissionedFile->NewKANGISFileNo,
                    'file_name' => $decommissionedFile->FileName,
                    'commissioning_date' => $decommissionedFile->commissioning_date ? Carbon::parse($decommissionedFile->commissioning_date)->format('d M Y, h:i A') : 'N/A',
                    'decommissioning_date' => $decommissionedFile->decommissioning_date ? Carbon::parse($decommissionedFile->decommissioning_date)->format('d M Y, h:i A') : 'N/A',
                    'decommissioning_reason' => $decommissionedFile->decommissioning_reason,
                    'decommissioned_by' => trim($decommissionedFile->updated_by ?? '') ?: (trim($decommissionedFile->created_by ?? '') ?: 'System'),
                    'created_at' => $decommissionedFile->updated_at ? Carbon::parse($decommissionedFile->updated_at)->format('d M Y, h:i A') : ($decommissionedFile->created_at ? Carbon::parse($decommissionedFile->created_at)->format('d M Y, h:i A') : 'N/A')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving decommissioned file details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search for files by file number
     */
    public function searchFiles(Request $request)
    {
        $search = $request->get('search', '');
        
        if (empty($search)) {
            return response()->json([]);
        }

        try {
            $files = FileNumber::active()
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->where(function($query) use ($search) {
                    $query->where('mlsfNo', 'like', "%{$search}%")
                          ->orWhere('kangisFileNo', 'like', "%{$search}%")
                          ->orWhere('NewKANGISFileNo', 'like', "%{$search}%")
                          ->orWhere('FileName', 'like', "%{$search}%");
                })
                ->select(['id', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'FileName'])
                ->limit(20)
                ->get();

            $results = $files->map(function($file) {
                $displayText = '';
                if ($file->mlsfNo) $displayText .= $file->mlsfNo . ' - ';
                if ($file->FileName) $displayText .= $file->FileName;
                
                return [
                    'id' => $file->id,
                    'text' => trim($displayText, ' - '),
                    'mlsfNo' => $file->mlsfNo,
                    'kangisFileNo' => $file->kangisFileNo,
                    'NewKANGISFileNo' => $file->NewKANGISFileNo,
                    'FileName' => $file->FileName
                ];
            });

            return response()->json($results);

        } catch (\Exception $e) {
            \Log::error('Error searching files: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    /**
     * Get statistics for dashboard
     */
    public function getStatistics()
    {
        try {
            $conn = \Illuminate\Support\Facades\DB::connection('sqlsrv');

            $totalFiles = FileNumber::where(function($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })->count();

            $activeFiles = FileNumber::active()
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->count();

            // Count from both sources
            $decommissionedFromFileNumber = FileNumber::decommissioned()
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->count();

            $decommissionedFromArchive = $conn->table('decommissioned_files')->count();

            $decommissionedFiles = $decommissionedFromFileNumber + $decommissionedFromArchive;

            $recentFromFileNumber = FileNumber::decommissioned()
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->where('decommissioning_date', '>=', now()->subDays(30))
                ->count();

            $recentFromArchive = $conn->table('decommissioned_files')
                ->where('decommissioning_date', '>=', now()->subDays(30))
                ->count();

            $recentDecommissioned = $recentFromFileNumber + $recentFromArchive;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_files' => $totalFiles + $decommissionedFromArchive,
                    'active_files' => $activeFiles,
                    'decommissioned_files' => $decommissionedFiles,
                    'recent_decommissioned' => $recentDecommissioned
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}