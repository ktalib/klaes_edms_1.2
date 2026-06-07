<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use App\Models\Phs\PhsMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class PhsOrganizationController extends Controller
{
    /** The console shell (super_admin only — guarded by phs.admin middleware). */
    public function index()
    {
        $member = Auth::guard('phs')->user();
        $institution = $member->institution;

        return view('phs.organization.index', [
            'member' => $member,
            'institution' => $institution,
            'members' => $institution->members()->orderBy('id')->get(),
        ]);
    }

    public function listMembers()
    {
        $institution = Auth::guard('phs')->user()->institution;
        return response()->json([
            'success' => true,
            'data' => $institution->members()->orderBy('id')->get(),
        ]);
    }

    public function storeMember(Request $request)
    {
        $institution = Auth::guard('phs')->user()->institution;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:sqlsrv.phs_members,email'],
            'password' => ['required', 'string', 'min:6'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'user_type' => ['required', 'in:super_admin,regular_user'],
            'access_role' => ['required', 'in:search_only,report_viewer,analytics_viewer'],
        ]);

        $member = $institution->members()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'job_title' => $data['job_title'] ?? null,
            'department' => $data['department'] ?? null,
            'user_type' => $data['user_type'],
            'access_role' => $data['access_role'],
            'status' => 'active',
        ]);

        return response()->json(['success' => true, 'message' => 'Team member added.', 'data' => $member]);
    }

    public function updateMember(Request $request, $id)
    {
        $institution = Auth::guard('phs')->user()->institution;
        $member = $institution->members()->where('id', $id)->firstOrFail();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'user_type' => ['sometimes', 'in:super_admin,regular_user'],
            'access_role' => ['sometimes', 'in:search_only,report_viewer,analytics_viewer'],
            'status' => ['sometimes', 'in:active,suspended'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        if (!empty($data['password'])) {
            $member->password = Hash::make($data['password']);
        }
        unset($data['password']);
        $member->fill($data)->save();

        return response()->json(['success' => true, 'message' => 'Member updated.', 'data' => $member]);
    }

    public function destroyMember($id)
    {
        $current = Auth::guard('phs')->user();
        $institution = $current->institution;
        $member = $institution->members()->where('id', $id)->firstOrFail();

        if ($member->id === $current->id) {
            return response()->json(['success' => false, 'message' => 'You cannot remove your own account.'], 422);
        }

        // Keep at least one super admin.
        if ($member->isSuperAdmin() && $institution->members()->where('user_type', 'super_admin')->count() <= 1) {
            return response()->json(['success' => false, 'message' => 'At least one administrator must remain.'], 422);
        }

        $member->delete();
        return response()->json(['success' => true, 'message' => 'Member removed.']);
    }

    public function activity()
    {
        $institution = Auth::guard('phs')->user()->institution;

        $searches = $institution->searchLogs()->with('member')->orderByDesc('id')->limit(50)->get()
            ->map(fn($r) => [
                'type' => 'search',
                'description' => 'Search: ' . ($r->query ?: $r->file_number),
                'member' => optional($r->member)->name,
                'reference' => $r->reference_no,
                'at' => optional($r->created_at)->toDateTimeString(),
            ]);

        $txns = $institution->transactions()->with('member')->orderByDesc('id')->limit(50)->get()
            ->map(fn($r) => [
                'type' => $r->type,
                'description' => ucfirst(str_replace('_', ' ', $r->type)) . ': ' . $r->tokens . ' tokens' . ($r->package_name ? " ({$r->package_name})" : ''),
                'member' => optional($r->member)->name,
                'reference' => $r->reference_no,
                'at' => optional($r->created_at)->toDateTimeString(),
            ]);

        $merged = $searches->concat($txns)->sortByDesc('at')->values();

        return response()->json(['success' => true, 'data' => $merged]);
    }

    public function updateBranding(Request $request)
    {
        $institution = Auth::guard('phs')->user()->institution;

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'banner' => ['nullable', 'image', 'max:4096'],
        ]);

        if (!empty($data['name'])) {
            $institution->name = $data['name'];
        }
        if (!empty($data['primary_color'])) {
            $institution->primary_color = $data['primary_color'];
        }
        if (!empty($data['secondary_color'])) {
            $institution->secondary_color = $data['secondary_color'];
        }

        if ($request->hasFile('logo')) {
            if ($institution->logo_path) {
                Storage::disk('public')->delete($institution->logo_path);
            }
            $institution->logo_path = $request->file('logo')->store('phs/logos', 'public');
        }
        if ($request->hasFile('banner')) {
            if ($institution->banner_path) {
                Storage::disk('public')->delete($institution->banner_path);
            }
            $institution->banner_path = $request->file('banner')->store('phs/banners', 'public');
        }

        $institution->save();

        return response()->json([
            'success' => true,
            'message' => 'Branding updated.',
            'data' => [
                'name' => $institution->name,
                'primary_color' => $institution->primary_color,
                'secondary_color' => $institution->secondary_color,
                'logo_url' => $institution->logo_path ? asset('storage/' . $institution->logo_path) : null,
                'banner_url' => $institution->banner_path ? asset('storage/' . $institution->banner_path) : null,
            ],
        ]);
    }
}
