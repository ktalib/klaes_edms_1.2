<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\LoggedHistory;
use App\Models\Notification;
use App\Models\PackageTransaction;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserRole;
use App\Models\UserType;
use App\Models\UserLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage; // added for profile image storage
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\UserNotificationService;
use App\Services\Payroll\WorkstationPaymentStructureService;
use App\Support\Concerns\ResolvesWorkStations;

class UserController extends Controller
{
    use ResolvesWorkStations;
    public function __construct(
        protected UserNotificationService $notificationService,
        protected WorkstationPaymentStructureService $structureService
    ) {
    }

    public function index()
    {
        $PageTitle = __('User');
        $PageDescription = __('User List');

        $parentId = parentId();
        $currentUser = Auth::user();
        $shouldFilterByParent = !empty($parentId)
            && $currentUser
            && !in_array($currentUser->type, ['super admin', 'owner'], true);

        $users = User::query()
            ->select([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone_number',
                'department_id',
                'assign_role',
                'profile',
                'user_level',
                'type',
                'work_days_per_week',
                'man_hours_per_day',
                'staff_type_category',
                'created_at',
                'parent_id',
                'is_active',
            ])
            ->with(['department:id,name'])
            ->when($shouldFilterByParent, function ($query) use ($parentId) {
                $query->where('parent_id', $parentId);
            })
            ->orderByDesc('id')
            ->get();

        return view('user.index', compact('users', 'PageTitle', 'PageDescription'));
    }


    public function create()
    {
        try {
            // Get departments using the default connection
            $departments = Department::where('is_active', 1)->pluck('name', 'id');

            // Get user types using sqlsrv connection
            $userTypes = UserType::on('sqlsrv')
                ->active()
                ->orderByPriority()
                ->get(['id', 'name', 'code', 'description']);

            // Get user roles using sqlsrv connection
            $userRoles = UserRole::on('sqlsrv')
                ->where('is_active', 1)
                ->get(['id', 'name', 'department_id', 'level', 'user_type']);

            $workStations = $this->workStationOptions();

            $attendanceShifts = config('attendance.shifts', []);

            $staffTypeOptions = DB::connection('sqlsrv')
                ->table('payroll_staff_types')
                ->orderBy('code')
                ->get(['code', 'name'])
                ->mapWithKeys(fn ($type) => [$type->code => sprintf('%s - %s', $type->code, $type->name)])
                ->toArray();

            $paymentStructures = $this->structureService->dropdownOptions();

            // Officer ranks (seniority) for file-request prioritisation.
            $ranks = config('file_request_priority.options', []);

            return view('user.create', compact(
                'departments',
                'userTypes',
                'userRoles',
                'workStations',
                'attendanceShifts',
                'staffTypeOptions',
                'paymentStructures',
                'ranks'
            ));
        } catch (\Exception $e) {
            \Log::error('Error in UserController@create', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()->with('error', 'Error loading user creation form: ' . $e->getMessage());
        }
    }

    /**
     * Get user levels for a specific user type
     */
    public function getUserLevels($userTypeId)
    {
        try {
            $userLevels = UserLevel::on('sqlsrv')
                                  ->forUserType($userTypeId)
                                  ->active()
                                  ->get(['id', 'name', 'code', 'description']);
            
            \Log::info('User levels fetched', [
                'user_type_id' => $userTypeId,
                'levels_count' => $userLevels->count(),
                'levels' => $userLevels->toArray()
            ]);
            
            return response()->json($userLevels);
        } catch (\Exception $e) {
            \Log::error('Error fetching user levels', [
                'user_type_id' => $userTypeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to load user levels: ' . $e->getMessage()
            ], 500);
        }
    }


    public function store(Request $request)
    {
        if (\Auth::user()->type == 'super admin') {
                $validator = \Validator::make(
                    $request->all(),
                    [
                        'name' => 'required',
                        'username' => 'required|unique:users', // add username validation
                        'password' => 'required|min:6',
                        'work_station' => $this->workStationValidationRule(false),
                    ]
                );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();

                    return redirect()->back()->with('error', $messages->first());
                }

                $user = new User();
                $user->name = $request->name;
                $user->username = $request->username; // save username
                $user->email = $request->filled('email') ? $request->email : uniqid('temp_') . '@klaes.com.ng';
                $user->assign_role = isset($request->user_role) ? implode(',', $request->user_role) : null;
                $user->password = \Hash::make($request->password);
                // allow either phone or phone_number
                $user->phone_number = $request->input('phone_number', $request->input('phone'));
                $user->department_id = $request->department_id; // Save department_id
                $user->rank = $request->filled('rank') ? $request->rank : null; // seniority for file-request priority
                $user->work_station = $request->work_station;
                $user->user_level = $request->user_level; // Save user_level
                $user->type = 'owner';
                $user->lang = 'english';
                $user->subscription = 1;
                $user->parent_id = parentId();
                $user->email_verified_at = now();

                $defaultShiftCode = $this->defaultShiftCode();
                $requestedShiftCode = $request->shift_code ?? $defaultShiftCode;
                $resolvedShiftCode = $this->resolveShiftCode($requestedShiftCode);
                $user->shift_code = $resolvedShiftCode ?? $defaultShiftCode;
                $user->auto_deactivate = true;
                
                // Handle optional profile image upload
                if ($request->hasFile('profile')) {
                    $file = $request->file('profile');
                    $filename = uniqid('profile_') . '_' . time() . '.' . $file->getClientOriginalExtension();
                    // store in public disk under upload/profile
                    Storage::disk('public')->putFileAs('upload/profile', $file, $filename);
                    $user->profile = $filename;
                    $user->passport_photo_path = 'upload/profile/' . $filename;
                } else {
                    $user->profile = 'avatar.png';
                }

                $user->save();
                defaultTemplate($user->id);

                $module = 'user_create';
                $notification = Notification::where('parent_id', parentId())->where('module', $module)->first();
                $setting = settings();
                $errorMessage = '';
                if (!empty($notification) && $notification->enabled_email == 1) {
                    $notification_responce = MessageReplace($notification, $user->id);
                    $data['subject'] = $notification_responce['subject'];
                    $data['message'] = $notification_responce['message'];
                    $data['module'] = $module;
                    $data['password'] = $request->password;
                    $data['logo'] = $setting['company_logo'];
                    $to = $user->email;

                    $response = commonEmailSend($to, $data);
                    if ($response['status'] == 'error') {
                        $errorMessage = '<br><span class="text-danger">' . $response['message'] . '</span>';
                    }
                }
                return redirect()->route('users.index')->with('success', __('User successfully created.') . $errorMessage);
            } else {

                $shiftOptions = array_keys(config('attendance.shifts', []));
                $shiftRule = ['nullable', 'string'];
                if (!empty($shiftOptions)) {
                    $shiftRule[] = Rule::in($shiftOptions);
                }

                $validator = \Validator::make(
                    $request->all(),
                    [
                        'first_name' => 'required',
                        'last_name' => 'required',
                        'password' => 'required|min:6',
                        'department_id' => 'required',
                        'user_type' => 'required|string|in:Management,Operations,ALL,User,System',
                        'user_level' => 'required|string|in:Administrative,Technical,Finance,Lowest,High,Highest',
                        'user_role' => 'required|array',
                        'rank' => 'nullable|string|max:255',
                        'work_station' => $this->workStationValidationRule(false),
                        'work_days_per_week' => 'nullable|integer|in:2,5,7',
                        'man_hours_per_day' => 'nullable|integer|in:4,8',
                        'staff_type_category' => 'required|string|in:MDC,MLPP,MDCM',
                        'shift_code' => $shiftRule,
                        'auto_deactivate' => 'nullable|boolean',
                        'payment_structure_id' => 'nullable|integer|exists:sqlsrv.workstation_payment_structures,id',
                        'base_salary_override' => 'nullable|numeric|min:0',
                    ]
                );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();

                    return redirect()->back()->with('error', $messages->first());
                }

                $pricing_feature_settings = getSettingsValByIdName(1, 'pricing_feature');
                if ($pricing_feature_settings == 'on') {
                    $ids = parentId();
                    $authUser = \App\Models\User::find($ids);
                    if ($authUser) {
                        $totalUser = $authUser->totalUser();
                        $subscription = Subscription::find($authUser->subscription);
                        if ($totalUser >= $subscription->user_limit && $subscription->user_limit != 0) {
                            return redirect()->back()->with('error', __('Your user limit is over, please upgrade your subscription.'));
                        }
                    }
                }
                
                $user = new User();
                $user->first_name = $request->first_name;
                $user->last_name = $request->last_name;
                $user->username = $request->username; // save username
                $user->email = $request->filled('email') ? $request->email : uniqid('temp') . '@klaes.com.ng';
                // allow either phone or phone_number
                $user->phone_number = $request->input('phone_number', $request->input('phone'));
                $user->password = \Hash::make($request->password);
                $user->department_id = $request->department_id;
                $user->user_level = $request->user_level;
                $user->work_station = $request->input('work_station');
                $user->type = $request->user_type; // Use the selected user type
                $user->work_days_per_week = $request->filled('work_days_per_week')
                    ? (int) $request->work_days_per_week
                    : null;
                $user->man_hours_per_day = $request->filled('man_hours_per_day')
                    ? (int) $request->man_hours_per_day
                    : null;
                $user->staff_type_category = $request->staff_type_category;
                $defaultShiftCode = $this->defaultShiftCode();
                $requestedShiftCode = $request->input('shift_code', $defaultShiftCode);
                $resolvedShiftCode = $this->resolveShiftCode($requestedShiftCode);
                $user->shift_code = $resolvedShiftCode ?? $defaultShiftCode;
                $normalizedStaffType = strtoupper(trim($request->staff_type_category ?? ''));
                $shouldForceAccessEnabled = in_array($normalizedStaffType, ['MLPP', 'MDCM'], true);
                $user->auto_deactivate = $shouldForceAccessEnabled
                    ? true
                    : $request->boolean('auto_deactivate', true);
                $user->is_pc_access = $shouldForceAccessEnabled
                    ? true
                    : $request->boolean('is_pc_access');
                $structureId = $request->filled('payment_structure_id')
                    ? (int) $request->payment_structure_id
                    : null;
                $overrideValue = $request->filled('base_salary_override')
                    ? (float) $request->base_salary_override
                    : null;

                $user->workstation_payment_structure_id = $structureId;
                $user->base_salary_override = $overrideValue;
                $user->base_salary_source = $overrideValue !== null
                    ? 'override'
                    : ($structureId ? 'structure' : null);
                $user->email_verified_at = now();
                
                // Handle required profile image upload
                if ($request->hasFile('profile')) {
                    $file = $request->file('profile');
                    $filename = uniqid('profile_') . '_' . time() . '.' . $file->getClientOriginalExtension();
                    Storage::disk('public')->putFileAs('upload/profile', $file, $filename);
                    $user->profile = $filename;
                    $user->passport_photo_path = 'upload/profile/' . $filename;
                } else {
                    // Fallback, though validation requires it
                    $user->profile = 'avatar.png';
                    $user->passport_photo_path = $user->profile ? 'upload/profile/' . $user->profile : null;
                }
                
                $user->lang = 'english';
                $user->parent_id = parentId();
                $user->rank = $request->filled('rank') ? $request->rank : null; // seniority for file-request priority
                $user->assign_role = isset($request->user_role) ? implode(',', $request->user_role) : null;
                $user->user_actions = isset($request->user_actions) ? implode(',', $request->user_actions) : null;
                $user->dfr_permissions = isset($request->dfr_permissions) ? implode(',', $request->dfr_permissions) : null;
                $user->fr_permissions = $request->input('fr_permissions') === 'SCB' ? 'SCB' : null;
                $user->save();

                $module = 'user_create';
                $notification = Notification::where('parent_id', parentId())->where('module', $module)->first();
                if (!empty($notification)) {
                    $notification->password=$request->password;
                }
                $setting = settings();
                $errorMessage = '';
                if (!empty($notification) && $notification->enabled_email == 1) {
                    $notification_responce = MessageReplace($notification, $user->id);
                    $data['subject'] = $notification_responce['subject'];
                    $data['message'] = $notification_responce['message'];
                    $data['module'] = $module;
                    $data['password'] = $request->password;
                    $data['logo'] = $setting['company_logo'];
                    $to = $user->email;

                    $response = commonEmailSend($to, $data);
                    if ($response['status'] == 'error') {
                        $errorMessage=$response['message'];
                    }
                }

                return redirect()->route('users.index')->with('success', __('User successfully created.'));
            }
    }


    public function show(User $user)
    {
        $settings = settings();
        $transactions = PackageTransaction::where('user_id', $user->id)->orderBy('created_at', 'DESC')->get();
        // Remove subscriptions variable
        return view('user.show', compact('user', 'transactions', 'settings'));
    }


    public function edit($id)
    {
        $user = User::findOrFail($id);
        $departments = Department::where('is_active', 1)->pluck('name', 'id');
        
        // Get user roles with user_type and level columns (same as create method)
        $userRoles = UserRole::on('sqlsrv')
                           ->where('is_active', 1)
                           ->get(['id', 'name', 'department_id', 'level', 'user_type']);
        
        // Get user's assigned roles as an array
        $userAssignedRoles = !empty($user->assign_role) ? explode(',', $user->assign_role) : [];
        
        $workStations = $this->workStationOptions();

        $staffTypeOptions = DB::connection('sqlsrv')
            ->table('payroll_staff_types')
            ->orderBy('code')
            ->get(['code', 'name'])
            ->mapWithKeys(fn ($type) => [$type->code => sprintf('%s - %s', $type->code, $type->name)])
            ->toArray();

        $paymentStructures = $this->structureService->dropdownOptions();
        $attendanceShifts = config('attendance.shifts', []);

        // Officer ranks (seniority) for file-request prioritisation.
        $ranks = config('file_request_priority.options', []);

        return view('user.edit', compact(
            'user',
            'departments',
            'userRoles',
            'userAssignedRoles',
            'workStations',
            'attendanceShifts',
            'staffTypeOptions',
            'paymentStructures',
            'ranks'
        ));
    }


    public function update(Request $request, $id)
    {
        if (\Auth::user()->type == 'super admin') {
                $user = User::findOrFail($id);

                $validator = \Validator::make(
                    $request->all(),
                    [
                        'name' => 'required',
                        'username' => 'required|unique:users,username,' . $id, // add username validation
                        'work_station' => $this->workStationValidationRule(false),
                        'password' => 'nullable|min:6',
                    ]
                );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();

                    return redirect()->back()->with('error', $messages->first());
                }

                $userData = $request->all();
                $user->fill($userData);
                $user->username = $request->username; // update username
                $user->email = $request->filled('email') ? $request->email : uniqid('temp') . '@klaes.com.ng';
                if ($request->filled('password')) {
                    $user->password = Hash::make($request->password);
                }
                $user->save();
                $this->notifyAccountUpdate($user);

                return redirect()->route('users.index')->with('success', 'User successfully updated.');
            } else {
                $shiftOptions = array_keys(config('attendance.shifts', []));
                $shiftRule = ['nullable', 'string'];
                if (!empty($shiftOptions)) {
                    $shiftRule[] = Rule::in($shiftOptions);
                }

                $validator = \Validator::make(
                    $request->all(),
                    [
                        'first_name' => 'required',
                        'last_name' => 'required',
                        'department_id' => 'required',
                        'user_type' => 'required|string|in:Management,Operations,ALL,User,System',
                        'user_level' => 'required|string|in:Administrative,Technical,Finance,Lowest,Highest,High',
                        'user_role' => 'required|array',
                        'rank' => 'nullable|string|max:255',
                        'work_station' => $this->workStationValidationRule(false),
                        'work_days_per_week' => 'nullable|integer|in:2,5,7',
                        'man_hours_per_day' => 'nullable|integer|in:4,8',
                        'staff_type_category' => 'required|string|in:MDC,MLPP,MDCM',
                        'shift_code' => $shiftRule,
                        'auto_deactivate' => 'nullable|boolean',
                        'payment_structure_id' => 'nullable|integer|exists:sqlsrv.workstation_payment_structures,id',
                        'base_salary_override' => 'nullable|numeric|min:0',
                        'password' => 'nullable|min:6',
                    ]
                );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();

                    return redirect()->back()->with('error', $messages->first());
                }

                // Get the first selected role to set as the user type
                $firstRoleName = $request->user_role[0];
                $userRole = UserRole::where('name', $firstRoleName)->first();
                
                $user = User::findOrFail($id);
                $user->first_name = $request->first_name;
                $user->last_name = $request->last_name;
                $user->username = $request->username; // update username
                $user->email = $request->filled('email') ? $request->email : uniqid('temp') . '@klaes.com.ng';
                $user->phone_number = $request->phone_number;
                $user->department_id = $request->department_id;
                $user->rank = $request->filled('rank') ? $request->rank : null; // seniority for file-request priority
                $user->user_level = $request->user_level;
                if ($request->has('work_station')) {
                    $user->work_station = $request->input('work_station');
                }
                if ($request->has('work_days_per_week')) {
                    $user->work_days_per_week = $request->filled('work_days_per_week')
                        ? (int) $request->work_days_per_week
                        : null;
                }
                if ($request->has('man_hours_per_day')) {
                    $user->man_hours_per_day = $request->filled('man_hours_per_day')
                        ? (int) $request->man_hours_per_day
                        : null;
                }
                $user->staff_type_category = $request->staff_type_category;
                $normalizedStaffType = strtoupper(trim($request->staff_type_category ?? ''));
                $shouldForceAccessEnabled = in_array($normalizedStaffType, ['MLPP', 'MDCM'], true);
                if ($request->has('shift_code')) {
                    $user->shift_code = $this->resolveShiftCode($request->input('shift_code'));
                }
                if ($shouldForceAccessEnabled) {
                    $user->auto_deactivate = true;
                    $user->is_pc_access = true;
                } else {
                    if ($request->has('auto_deactivate')) {
                        $user->auto_deactivate = $request->boolean('auto_deactivate', (bool) ($user->auto_deactivate ?? true));
                    }
                    if ($request->has('is_pc_access')) {
                        $user->is_pc_access = $request->boolean('is_pc_access', (bool) ($user->is_pc_access ?? false));
                    }
                }
                $structureId = $request->filled('payment_structure_id')
                    ? (int) $request->payment_structure_id
                    : null;
                $overrideValue = $request->filled('base_salary_override')
                    ? (float) $request->base_salary_override
                    : null;

                $user->workstation_payment_structure_id = $structureId;
                $user->base_salary_override = $overrideValue;
                $user->base_salary_source = $overrideValue !== null
                    ? 'override'
                    : ($structureId ? 'structure' : null);
                $user->type = $request->user_type;
                $user->assign_role = isset($request->user_role) ? implode(',', $request->user_role) : null;
                $user->user_actions = isset($request->user_actions) ? implode(',', $request->user_actions) : null;
                $user->dfr_permissions = isset($request->dfr_permissions) ? implode(',', $request->dfr_permissions) : null;
                $user->fr_permissions = $request->input('fr_permissions') === 'SCB' ? 'SCB' : null;
                if ($request->filled('password')) {
                    $user->password = Hash::make($request->password);
                }
                $user->save();
                $this->notifyAccountUpdate($user);
                
                return redirect()->route('users.index')->with('success', 'User successfully updated.');
            }
    }


    public function suspend(User $user)
    {
        if ((int) $user->id === (int) Auth::id()) {
            return redirect()->route('users.index')->with('error', __('You cannot suspend your own account.'));
        }

        if ((int) $user->is_active === 0) {
            return redirect()->route('users.index')->with('success', __('User already suspended.'));
        }

        $user->is_active = 0;
        $user->save();

        return redirect()->route('users.index')->with('success', __('User successfully suspended.'));
    }

    public function unsuspend(User $user)
    {
        if ((int) $user->is_active === 1) {
            return redirect()->route('users.index')->with('success', __('User already active.'));
        }

        $user->is_active = 1;
        $user->save();

        return redirect()->route('users.index')->with('success', __('User successfully reactivated.'));
    }

    public function destroy($id)
    {
        $user = User::find($id);
        $user->delete();

        return redirect()->route('users.index')->with('success', __('User successfully deleted.'));
    }

    protected function defaultShiftCode(): ?string
    {
        $shifts = array_keys(config('attendance.shifts', []));

        return $shifts[0] ?? null;
    }

    protected function resolveShiftCode(?string $code): ?string
    {
        $shifts = array_keys(config('attendance.shifts', []));
        if (empty($shifts)) {
            return null;
        }

        if ($code === null || $code === '') {
            return null;
        }

        return in_array($code, $shifts, true)
            ? $code
            : null;
    }

    protected function notifyAccountUpdate(User $user): void
    {
        try {
            $actor = Auth::user();
            $actorName = $actor?->name
                ?? trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? ''))
                ?: ($actor?->email ?? 'System');

            $accountName = $user->name
                ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
                ?: ($user->username ?? 'Account');

            $this->notificationService->create(
                (int) $user->id,
                'file_tracking.account.updated',
                'Account details updated',
                sprintf('%s updated the profile for %s.', $actorName, $accountName),
                [
                    'updated_by' => $actor?->id,
                    'updated_by_name' => $actorName,
                    'account_name' => $accountName,
                ],
                [
                    'module' => 'file_tracking',
                    'subject' => 'Account details updated',
                    'message' => sprintf('%s updated the profile for %s.', $actorName, $accountName),
                ]
            );
        } catch (\Throwable $exception) {
            \Log::warning('Unable to send account update notification', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function loggedHistory()
    {
        $histories = LoggedHistory::where('parent_id', parentId())->get();
        return view('logged_history.index', compact('histories'));
    }

    public function loggedHistoryShow($id)
    {
        $histories = LoggedHistory::find($id);
        return view('logged_history.show', compact('histories'));
    }

    public function loggedHistoryDestroy($id)
    {
        $histories = LoggedHistory::find($id);
        $histories->delete();
        return redirect()->back()->with('success', 'Logged history succefully deleted.');
    }

}
