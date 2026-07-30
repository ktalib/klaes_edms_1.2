<?php

namespace App\Http\Controllers;

use App\Models\LandOfficer;
use App\Models\User;
use App\Support\Concerns\ResolvesWorkStations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use ResolvesWorkStations;

    public function index()
    {
        $PageTitle = 'User Profile';
        $PageDescription = 'Manage your profile information';
        $user = Auth::user();
        $workStationOptions = $this->workStationOptions();
        $canEditWorkStation = empty($user->work_station);
        $deputyOptions = User::deputyOptions($user->id);
        // Officer ranks (seniority) for file-request prioritisation.
        $ranks = \App\Models\OfficerRank::options();

        return view('profile.index', compact(
            'PageTitle',
            'PageDescription',
            'workStationOptions',
            'canEditWorkStation',
            'deputyOptions',
            'ranks'
        ));
    }


    public function update(Request $request)
    {
        // Validate the incoming request
        $user = Auth::user();
        $workStationRule = $user->work_station
            ? ['prohibited']
            : $this->workStationValidationRule();
        
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($user->id),
            ],
            'phone_number' => 'nullable|string|max:20',
            'rank' => 'nullable|string|max:255',
            'rank_other' => 'nullable|string|max:255|required_if:rank,' . \App\Models\OfficerRank::OTHER_VALUE,
            'password' => 'nullable|string|min:8|confirmed',
            'profile' => 'nullable',
            'signature_file' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'work_station' => $workStationRule,
            'is_on_leave' => 'nullable|boolean',
            'leave_start_date' => 'nullable|date',
            'leave_end_date' => 'nullable|date|after_or_equal:leave_start_date',
            'deputy_user_id' => ['nullable', 'integer', 'exists:sqlsrv.users,id', Rule::notIn([$user->id])],
            'out_of_office_from' => 'nullable|date',
            'out_of_office_to' => 'nullable|date|after_or_equal:out_of_office_from',
        ]);

        // Handle profile image upload if provided
        if ($request->hasFile('profile')) {
            // Delete old profile image if it exists
            if ($user->profile && Storage::exists('public/' . $user->profile)) {
                Storage::delete('public/' . $user->profile);
            }
            
            // Store the new image
            $profilePath = $request->file('profile')->store('profiles', 'public');
            $user->profile = $profilePath;
        }

        // Store/update Lands 12 signature against the user's linked land officer profile.
        if ($request->hasFile('signature_file')) {
            $officer = LandOfficer::where('user_id', $user->id)->first();

            if ($officer && !empty($officer->signature_file)) {
                $oldPath = $officer->signature_file;
                // Old paths: 'public/land_officer_signatures/...' stored on local disk
                // New paths: 'land_officer_signatures/...' stored on public disk
                if (str_starts_with($oldPath, 'public/')) {
                    Storage::disk('local')->delete($oldPath);
                } else {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            // Store on the 'public' disk so the file is web-accessible via /storage/...
            $signaturePath = $request->file('signature_file')->store('land_officer_signatures', 'public');

            if (!$officer) {
                $officer = new LandOfficer();
                $officer->user_id = $user->id;
                $officer->name = trim((string) (($user->first_name ?? '') . ' ' . ($user->last_name ?? '')));
                if ($officer->name === '') {
                    $officer->name = (string) ($user->name ?? $user->email);
                }
                $officer->notification_type = 'email';
            }

            $officer->signature_file = $signaturePath;
            $officer->save();
        }

        // Update user information
        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'];
        $user->email = $validated['email'];
        $user->phone_number = $validated['phone_number'] ?? null;
        // Seniority for file-request priority; "Other (specify)" adds to the lookup table.
        $user->rank = \App\Models\OfficerRank::resolveSubmittedRank($request->input('rank'), $request->input('rank_other'));

        // Only update password if provided
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
            $user->is_password_change = 1; // Mark password as changed
        }

        if (empty($user->work_station) && array_key_exists('work_station', $validated)) {
            $user->work_station = $validated['work_station'];
        }

        // Self-service out-of-office status
        $user->is_on_leave = $request->boolean('is_on_leave');
        $user->leave_start_date = $validated['leave_start_date'] ?? null;
        $user->leave_end_date = $validated['leave_end_date'] ?? null;
        $user->deputy_user_id = $validated['deputy_user_id'] ?? null;
        $user->out_of_office_from = $validated['out_of_office_from'] ?? null;
        $user->out_of_office_to = $validated['out_of_office_to'] ?? null;

        // Save the updated user
        $user->save();
        
        return redirect()->route('profile.index')
                        ->with('success', 'Profile updated successfully.');
    }
}
