<?php

namespace App\Http\Controllers;

use App\Models\NewInstrumentType; // Registration Module (New)
use App\Models\InstrumentType as LegacyInstrumentType; // PRA Module (Legacy)
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\InstrumentRegistrationService;

class InstrumentTypeController extends Controller
{
    public function __construct(protected InstrumentRegistrationService $registrationService)
    {
    }

    /**
     * Display a listing of the resource (For Instrument Registration Manager).
     * Uses NewInstrumentType.
     */
    public function index()
    {
        // Fetch all types (Registration Module)
        $types = NewInstrumentType::orderBy('name')->get();

        // Fetch all vaults to map data
        $vaults = DB::connection('sqlsrv')->table('instrument_number_vaults')->get()->keyBy('instrument_type');

        // Map vault data to types using resolved names
        $types->transform(function ($type) use ($vaults) {
            $vaultName = $this->registrationService->resolveVaultName($type->name);
            $vault = $vaults->get($vaultName);

            $type->last_volume = $vault->current_volume ?? null;
            $type->last_page = $vault->current_page ?? null;
            $type->last_serial = $vault->current_serial ?? null;
            $type->vault_source = $vaultName; // Expose the source vault name

            return $type;
        });

        return response()->json($types);
    }

    /**
     * Get all instrument types for dropdown/select options (For PRA / Legacy).
     * USES LEGACY TABLE.
     */
    public function getAll()
    {
        $instrumentTypes = LegacyInstrumentType::active()
            ->orderBy('InstrumentName')
            ->get(['InstrumentTypeID', 'InstrumentName', 'Description'])
            ->map(function ($type) {
                return [
                    'id' => $type->InstrumentTypeID,
                    'name' => $type->InstrumentName,
                    'description' => $type->Description
                ];
            })
            ->values();

        return response()->json($instrumentTypes);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:sqlsrv.new_instrument_types,name',
            'description' => 'nullable|string',
            'last_volume' => 'nullable|integer|min:0',
            'last_page' => 'nullable|integer|min:0',
            'last_serial' => 'nullable|integer|min:0',
        ]);

        return DB::transaction(function () use ($request) {
            $type = NewInstrumentType::create([
                'name' => $request->name,
                'description' => $request->description
            ]);

            // Initialize or update the vault for this instrument type
            $volume = $request->input('last_volume', 1);
            $page = $request->input('last_page', 1);
            $serial = $request->input('last_serial', 0);

            // Use resolved vault name to support shared vaults
            $vaultName = $this->registrationService->resolveVaultName($request->name);

            DB::connection('sqlsrv')->table('instrument_number_vaults')->updateOrInsert(
                ['instrument_type' => $vaultName],
                [
                    'current_volume' => $volume,
                    'current_page' => $page,
                    'current_serial' => $serial,
                    'updated_at' => now(),
                ]
            );

            return response()->json($type, 201);
        });
    }

    public function update(Request $request, $id)
    {
        $type = NewInstrumentType::findOrFail($id);
        $oldName = $type->name;

        // PROTECTED TYPES: Prevent renaming of types involved in shared vault logic
        $protectedTypes = ['Power of Attorney', 'Deed of Surrender and Release', 'Deed of Assignment', 'Deed of Gift'];
        if (in_array($oldName, $protectedTypes) && $request->name !== $oldName) {
            return response()->json(['message' => 'This instrument type is a System Protected type and cannot be renamed.'], 403);
        }

        $request->validate([
            'name' => 'required|string|unique:sqlsrv.new_instrument_types,name,' . $id,
            'description' => 'nullable|string',
            'last_volume' => 'nullable|integer|min:0',
            'last_page' => 'nullable|integer|min:0',
            'last_serial' => 'nullable|integer|min:0',
        ]);

        return DB::transaction(function () use ($request, $type, $oldName) {
            $type->update([
                'name' => $request->name,
                'description' => $request->description
            ]);

            // If name changed, we should probably rename the vault entry too (if not shared)
            // But with shared vaults, this complicates things. 
            // For now, let's assume renaming non-protected types renames their 1-to-1 vault.
            if ($oldName !== $request->name) {
                // Only rename vault if it matches the old name (1-to-1)
                DB::connection('sqlsrv')->table('instrument_number_vaults')
                    ->where('instrument_type', $oldName)
                    ->update(['instrument_type' => $request->name]);
            }

            // Update vault numbers if provided
            if ($request->hasAny(['last_volume', 'last_page', 'last_serial'])) {
                $updateData = ['updated_at' => now()];
                if ($request->has('last_volume'))
                    $updateData['current_volume'] = $request->last_volume;
                if ($request->has('last_page'))
                    $updateData['current_page'] = $request->last_page;
                if ($request->has('last_serial'))
                    $updateData['current_serial'] = $request->last_serial;

                $vaultName = $this->registrationService->resolveVaultName($request->name);

                DB::connection('sqlsrv')->table('instrument_number_vaults')
                    ->updateOrInsert(
                        ['instrument_type' => $vaultName],
                        $updateData
                    );
            }

            return response()->json($type);
        });
    }

    public function destroy($id)
    {
        $type = NewInstrumentType::findOrFail($id);

        // PROTECTED TYPES: Prevent deletion
        $protectedTypes = ['Power of Attorney', 'Deed of Surrender and Release', 'Deed of Assignment', 'Deed of Gift'];
        if (in_array($type->name, $protectedTypes)) {
            return response()->json(['message' => 'This instrument type is System Protected and cannot be deleted.'], 403);
        }

        $type->delete();

        return response()->json(null, 204);
    }
}