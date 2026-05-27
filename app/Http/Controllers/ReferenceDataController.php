<?php

namespace App\Http\Controllers;
   
use App\Models\District;
use App\Models\Lga;
use App\Models\LandUse;
use App\Models\Purpose;
use App\Models\StreetName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceDataController extends Controller
{
    public function lgas(): JsonResponse
    {
        $stateId = request()->get('state_id');

        if (!empty($stateId)) {
            $lgas = DB::connection('sqlsrv')
                ->table('StatLGAs')
                ->select(['StatLGAs.LGAID as id', 'StatLGAs.LGAName as name', 'StatLGAs.StateID as state_id'])
                ->when($stateId, function ($query) use ($stateId) {
                    $query->where('StatLGAs.StateID', $stateId);
                })
                ->orderBy('StatLGAs.LGAName')
                ->get();
        } else {
            $lgas = Lga::query()
                ->select(['lgas.id', 'lgas.name', 'lgas.code', 'lgas.slug'])
                ->where('lgas.is_active', true)
                ->orderBy('lgas.name')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $lgas,
        ]);
    }

    public function states(): JsonResponse
    {
        $states = DB::connection('sqlsrv')
            ->table('States')
            ->select(['StateID as id', 'StateName as name'])
            ->orderBy('StateName')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $states,
        ]);
    }

    public function districts(Request $request): JsonResponse
    {
        $query = District::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = trim($request->string('search'));
            $query->where('name', 'LIKE', "%{$search}%");
        }

        if ($request->filled('lga_id') && Schema::connection('sqlsrv')->hasColumn('districts', 'lga_id')) {
            $query->where('lga_id', $request->get('lga_id'));
        }

        $districts = $query->get(['id', 'name', 'slug']);

        return response()->json([
            'success' => true,
            'data' => $districts,
        ]);
    }

    public function landUses(): JsonResponse
    {
        $landUses = LandUse::query()
            ->select(['id', 'landuse as name'])
            ->orderBy('landuse')
            ->get()
            ->map(function ($lu) {
                $lu->prefixes = \App\Models\Prefix::where('land_use_id', $lu->id)
                    ->pluck('prefix')
                    ->toArray();
                return $lu;
            });

        return response()->json([
            'success' => true,
            'data' => $landUses,
        ]);
    }

    public function purposes(Request $request): JsonResponse
    {
        $query = Purpose::query()
            ->select(['id', 'name', 'landuseid'])
            ->orderBy('name');

        if ($request->filled('landuseid')) {
            $query->where('landuseid', $request->get('landuseid'));
        }

        $purposes = $query->get();

        return response()->json([
            'success' => true,
            'data' => $purposes,
        ]);
    }

    public function streets(Request $request): JsonResponse
    {
        $query = StreetName::query()
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = trim($request->string('search'));
            $query->where('name', 'LIKE', "%{$search}%");
        }

        if ($request->filled('district_id') && Schema::connection('sqlsrv')->hasColumn('street_names', 'district_id')) {
            $query->where('district_id', $request->get('district_id'));
        }

        $streets = $query->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $streets,
        ]);

        
    }
}
