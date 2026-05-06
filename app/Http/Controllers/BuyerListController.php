<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * BuyerListController - Standalone controller for buyer list CRUD operations
 * 
 * This controller handles all buyer-related operations including:
 * - Creating/Adding buyers (manual and CSV import)
 * - Reading/Retrieving buyer lists
 * - Updating individual buyers
 * - Deleting buyers
 * - CSV template download
 * 
 * Database Tables:
 * - buyer_list: Main buyer information (buyer_title, buyer_name, unit_no, cubic_easurement, application_id)
 * - st_unit_measurements: Unit measurement details (measurement, buyer_id, application_id, unit_no)
 * 
 * Field Name Mapping (for compatibility with step4-buyers.blade.php):
 * - buyerTitle -> buyer_title
 * - firstName + middleName + surname -> buyer_name (concatenated with spaces)
 * - unit_no -> unit_no (unchanged)
 * - unitMeasurement -> measurement (in st_unit_measurements table)
 * - cubicMeasurement -> cubic_easurement (in buyer_list table)
 * - landUse -> stored in buyer_list for reference
 */
class BuyerListController extends Controller
{
    /**
     * Get all buyers for a specific application
     * 
     * @param int $applicationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBuyersList($applicationId)
    {
        try {
            // Query the buyer_list table and join with st_unit_measurements
            // Use DISTINCT to avoid duplicate records when multiple measurements exist for same buyer
            $records = DB::connection('sqlsrv')
                ->table('buyer_list as bl')
                ->leftJoin('st_unit_measurements as sum', function($join) {
                    $join->on('bl.id', '=', 'sum.buyer_id')
                         ->on('bl.application_id', '=', 'sum.application_id');
                })
                ->where('bl.application_id', $applicationId)
                ->select(
                    'bl.id', 
                    'bl.buyer_title', 
                    'bl.buyer_name', 
                    'bl.unit_no', 
                    'bl.section_number',
                    'bl.land_use',
                    'bl.cubic_easurement',
                    'bl.unit_measurement_id', 
                    'sum.measurement',
                    'bl.created_at',
                    'bl.updated_at'
                )
                ->distinct()
                ->orderBy('bl.created_at', 'desc')
                ->get()
                ->toArray();

            return response()->json([
                'success' => true,
                'records' => $records,
                'count' => count($records)
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving buyers list: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add buyers manually (from form submission)
     * Supports both single and multiple buyers
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addBuyers(Request $request)
    {
        try {
            // Always extract application_id from the request
            $applicationId = $request->input('application_id');
            
            if (!$applicationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application ID is required.',
                    'errors' => ['application_id' => ['Application ID is required.']]
                ], 422);
            }

            // Extract records array
            $records = $request->input('records');
            if (!is_array($records)) {
                // Try to parse from JSON if sent as a string
                $records = json_decode($request->input('records'), true);
            }
            
            // If still not an array, try to build from form data
            if (!is_array($records)) {
                $records = [];
                foreach ($request->all() as $key => $value) {
                    if (preg_match('/^records\[(\d+)\]\[(\w+)\]$/', $key, $matches)) {
                        $index = $matches[1];
                        $field = $matches[2];
                        $records[$index][$field] = $value;
                    }
                }
                // Re-index array
                $records = array_values($records);
            }

            // Validate records array
            if (!is_array($records) || count($records) < 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'At least one buyer record is required.',
                    'errors' => ['records' => ['At least one buyer record is required.']]
                ], 422);
            }

            // Validate each record
            foreach ($records as $i => $record) {
                // Check for either firstName+surname OR buyerName
                $hasNames = !empty($record['firstName'] ?? '') && !empty($record['surname'] ?? '');
                $hasBuyerName = !empty($record['buyerName'] ?? '');
                
                if (!$hasNames && !$hasBuyerName) {
                    return response()->json([
                        'success' => false,
                        'message' => "Buyer name is required for buyer " . ($i + 1),
                        'errors' => ["records.$i.buyerName" => ['Buyer name is required.']]
                    ], 422);
                }
                
                if (empty($record['unit_no'] ?? '')) {
                    return response()->json([
                        'success' => false,
                        'message' => "Unit number is required for buyer " . ($i + 1),
                        'errors' => ["records.$i.unit_no" => ['Unit number is required.']]
                    ], 422);
                }

                if (empty($record['sectionNumber'] ?? '')) {
                    return response()->json([
                        'success' => false,
                        'message' => "Section number is required for buyer " . ($i + 1),
                        'errors' => ["records.$i.sectionNumber" => ['Section number is required.']]
                    ], 422);
                }
            }

            // Check if the application exists and get its status
            $application = DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $applicationId)
                ->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found.'
                ], 404);
            }

            // Check if both application status and planning recommendation are approved
            if ($application->application_status == 'Approved' && 
                $application->planning_recommendation_status == 'Approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot add buyers - Both Application Status and Planning Recommendation have been approved. No further modifications are allowed.'
                ], 403);
            }

            $insertedCount = 0;
            $skippedCount = 0;
            $errors = [];

            // Process each record
            foreach ($records as $record) {
                // Build buyer name (supports both formats)
                if (!empty($record['firstName'] ?? '') || !empty($record['surname'] ?? '')) {
                    $nameParts = [];
                    if (!empty($record['firstName'])) $nameParts[] = strtoupper(trim($record['firstName']));
                    if (!empty($record['middleName'])) $nameParts[] = strtoupper(trim($record['middleName']));
                    if (!empty($record['surname'])) $nameParts[] = strtoupper(trim($record['surname']));
                    $buyerName = implode(' ', $nameParts);
                } else {
                    $buyerName = strtoupper(trim($record['buyerName'] ?? ''));
                }
                
                $unitNo = strtoupper(trim($record['unit_no'] ?? ''));
                $sectionNumber = strtoupper(trim($record['sectionNumber'] ?? ''));
                $cubicMeasurement = isset($record['cubicMeasurement'])
                    ? trim($record['cubicMeasurement'])
                    : null;
                
                // Check if this buyer already exists
                $existing = DB::connection('sqlsrv')
                    ->table('buyer_list')
                    ->where('application_id', $applicationId)
                    ->where('buyer_name', $buyerName)
                    ->where('unit_no', $unitNo)
                    ->when($sectionNumber !== '', function ($query) use ($sectionNumber) {
                        $query->where('section_number', $sectionNumber);
                    })
                    ->first();

                if ($existing) {
                    $skippedCount++;
                    continue;
                }

                // Insert new buyer record
                $buyerId = DB::connection('sqlsrv')->table('buyer_list')->insertGetId([
                    'application_id' => $applicationId,
                    'buyer_title' => strtoupper(trim($record['buyerTitle'] ?? '')),
                    'buyer_name' => $buyerName,
                    'unit_no' => $unitNo,
                    'section_number' => $sectionNumber !== '' ? $sectionNumber : null,
                    'land_use' => !empty($record['landUse']) ? strtoupper(trim($record['landUse'])) : null,
                    'cubic_easurement' => $cubicMeasurement !== '' ? $cubicMeasurement : null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Handle measurement data if provided
                if (!empty($record['unitMeasurement'] ?? '')) {
                    // Check if measurement record already exists
                    $existingMeasurement = DB::connection('sqlsrv')
                        ->table('st_unit_measurements')
                        ->where('application_id', $applicationId)
                        ->where('unit_no', $unitNo)
                        ->first();

                    if ($existingMeasurement) {
                        // Update existing measurement
                        DB::connection('sqlsrv')
                            ->table('st_unit_measurements')
                            ->where('application_id', $applicationId)
                            ->where('unit_no', $unitNo)
                            ->update([
                                'buyer_id' => $buyerId,
                                'measurement' => $record['unitMeasurement'],
                                'updated_at' => now()
                            ]);
                    } else {
                        // Insert new measurement record
                        DB::connection('sqlsrv')->table('st_unit_measurements')->insert([
                            'application_id' => $applicationId,
                            'buyer_id' => $buyerId,
                            'unit_no' => $unitNo,
                            'measurement' => $record['unitMeasurement'],
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }

                $insertedCount++;
            }

            // Get updated records list for response
            $updatedRecords = $this->getBuyersListData($applicationId);

            $message = "Buyers saved successfully.";
            if ($insertedCount > 0 && $skippedCount > 0) {
                $message = "$insertedCount new buyer(s) added, $skippedCount duplicate(s) skipped.";
            } elseif ($insertedCount > 0) {
                $message = "$insertedCount new buyer(s) added successfully.";
            } elseif ($skippedCount > 0) {
                $message = "All buyers already exist. $skippedCount duplicate(s) skipped.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'records' => $updatedRecords,
                'count' => count($updatedRecords),
                'inserted' => $insertedCount,
                'skipped' => $skippedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Error adding buyers: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import buyers from CSV file
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function importCsv(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'application_id' => 'required|integer',
                'records' => 'required|array|min:1',
                'records.*.buyerTitle' => 'nullable|string',
                'records.*.firstName' => 'required|string',
                'records.*.surname' => 'required|string',
                'records.*.unit_no' => 'required|string',
                'records.*.sectionNumber' => 'required|string',
                'records.*.middleName' => 'nullable|string',
                'records.*.landUse' => 'nullable|string',
                'records.*.unitMeasurement' => 'nullable|numeric',
                'records.*.cubicMeasurement' => 'nullable',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Use the addBuyers method to process the CSV data
            return $this->addBuyers($request);

        } catch (\Exception $e) {
            Log::error('Error importing CSV: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a single buyer's information
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateBuyer(Request $request)
    {
        try {
            $validated = $request->validate([
                'application_id' => 'required|integer',
                'buyer_id'       => 'required|integer',
                'buyer_title'    => 'nullable|string',
                'buyer_name'     => 'required|string',
                'unit_no'        => 'required|string',
                'section_number' => 'nullable|string|max:100',
                'measurement'    => 'nullable|numeric',
                'land_use'       => 'nullable|string',
                'cubic_easurement' => 'nullable',
            ]);

            // Check if the application exists and get its status
            $application = DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $validated['application_id'])
                ->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found.'
                ], 404);
            }

            // Check if both application status and planning recommendation are approved
            if ($application->application_status == 'Approved' && 
                $application->planning_recommendation_status == 'Approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update buyer - Both Application Status and Planning Recommendation have been approved. No further modifications are allowed.'
                ], 403);
            }

            // Update the buyer record
            $updated = DB::connection('sqlsrv')
                ->table('buyer_list')
                ->where('id', $validated['buyer_id'])
                ->where('application_id', $validated['application_id'])
                ->update([
                    'buyer_title' => strtoupper(trim($validated['buyer_title'] ?? '')),
                    'buyer_name'  => strtoupper(trim($validated['buyer_name'])),
                    'unit_no'     => strtoupper(trim($validated['unit_no'])),
                    'section_number' => !empty($validated['section_number']) ? strtoupper(trim($validated['section_number'])) : null,
                    'land_use'    => !empty($validated['land_use']) ? strtoupper(trim($validated['land_use'])) : null,
                    'cubic_easurement' => array_key_exists('cubic_easurement', $validated)
                        ? ($validated['cubic_easurement'] !== null && $validated['cubic_easurement'] !== ''
                            ? trim($validated['cubic_easurement'])
                            : null)
                        : null,
                    'updated_at'  => now()
                ]);

            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Buyer not found or no changes made'
                ], 404);
            }

            // Handle measurement data if provided
            if (isset($validated['measurement']) && !empty($validated['measurement'])) {
                $existingMeasurement = DB::connection('sqlsrv')
                    ->table('st_unit_measurements')
                    ->where('application_id', $validated['application_id'])
                    ->where('unit_no', $validated['unit_no'])
                    ->first();

                if ($existingMeasurement) {
                    // Update existing measurement
                    DB::connection('sqlsrv')
                        ->table('st_unit_measurements')
                        ->where('application_id', $validated['application_id'])
                        ->where('unit_no', $validated['unit_no'])
                        ->update([
                            'buyer_id' => $validated['buyer_id'],
                            'measurement' => $validated['measurement'],
                            'updated_at' => now()
                        ]);
                } else {
                    // Insert new measurement record
                    DB::connection('sqlsrv')->table('st_unit_measurements')->insert([
                        'application_id' => $validated['application_id'],
                        'buyer_id' => $validated['buyer_id'],
                        'unit_no' => $validated['unit_no'],
                        'measurement' => $validated['measurement'],
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Buyer information updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating buyer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a buyer from the list
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteBuyer(Request $request)
    {
        try {
            $validated = $request->validate([
                'application_id' => 'required|integer',
                'buyer_id'       => 'required|integer',
            ]);

            // Check if the application exists and get its status
            $application = DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $validated['application_id'])
                ->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found.'
                ], 404);
            }

            // Check if both application status and planning recommendation are approved
            if ($application->application_status == 'Approved' && 
                $application->planning_recommendation_status == 'Approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete buyer - Both Application Status and Planning Recommendation have been approved. No further modifications are allowed.'
                ], 403);
            }

            // Delete the buyer record
            $deleted = DB::connection('sqlsrv')
                ->table('buyer_list')
                ->where('id', $validated['buyer_id'])
                ->where('application_id', $validated['application_id'])
                ->delete();

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Buyer not found'
                ], 404);
            }

            // Also delete the measurement record if it exists
            DB::connection('sqlsrv')
                ->table('st_unit_measurements')
                ->where('buyer_id', $validated['buyer_id'])
                ->where('application_id', $validated['application_id'])
                ->delete();

            // Get remaining records
            $records = $this->getBuyersListData($validated['application_id']);

            return response()->json([
                'success' => true,
                'message' => 'Buyer deleted successfully',
                'records' => $records,
                'count' => count($records)
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting buyer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download CSV template for buyer import
     * 
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadTemplate()
    {
        $headers = [
            'buyerTitle',
            'firstName',
            'middleName',
            'surname',
            'unit_no',
            'sectionNumber',
            'landUse',
            'unitMeasurement'
        ];
        
        $sampleData = [
            ['Mr.', 'JOHN', 'A', 'DOE', 'A101', 'SEC-01', 'RESIDENTIAL', '50.00'],
            ['Mrs.', 'JANE', 'B', 'SMITH', 'A102', 'SEC-02', 'COMMERCIAL', '75.50'],
            ['Dr.', 'ROBERT', '', 'JOHNSON', 'B201', 'SEC-03', 'INDUSTRIAL', '100.00']
        ];
        
        $filename = 'buyer_import_template_' . date('Y-m-d') . '.csv';
        
        return response()->streamDownload(function() use ($headers, $sampleData) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Write headers
            fputcsv($file, $headers);
            
            // Write sample data
            foreach ($sampleData as $row) {
                fputcsv($file, $row);
            }
            
            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Helper method to get buyers list data
     * 
     * @param int $applicationId
     * @return array
     */
    private function getBuyersListData($applicationId)
    {
        return DB::connection('sqlsrv')
            ->table('buyer_list as bl')
            ->leftJoin('st_unit_measurements as sum', function($join) {
                $join->on('bl.id', '=', 'sum.buyer_id')
                     ->on('bl.application_id', '=', 'sum.application_id');
            })
            ->where('bl.application_id', $applicationId)
            ->select(
                'bl.id', 
                'bl.buyer_title', 
                'bl.buyer_name', 
                'bl.unit_no', 
                'bl.section_number',
                'bl.land_use',
                'bl.unit_measurement_id', 
                'sum.measurement',
                'bl.created_at',
                'bl.updated_at'
            )
            ->distinct()
            ->orderBy('bl.created_at', 'desc')
            ->get()
            ->toArray();
    }
}
