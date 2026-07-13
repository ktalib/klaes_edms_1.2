<div class="modal-dialog w-full max-w-7xl shadow-none" role="document">
    <div class="mx-auto w-full">
        <div class="bg-white rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">{{ __('Edit User') }}</h3>
                <button type="button" class="text-gray-400 hover:text-gray-500 absolute top-4 right-4" data-dismiss="modal" aria-label="Close">
                    <span class="sr-only">Close</span>
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{ Form::model($user, ['route' => ['users.update', $user->id], 'method' => 'PUT']) }}

            @php
                $workStationOptions = collect($workStations ?? [])
                    ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                    ->mapWithKeys(fn ($value) => [$value => $value])
                    ->toArray();

                $staffTypeOptions = !empty($staffTypeOptions ?? [])
                    ? $staffTypeOptions
                    : [
                        'MDC' => __('MDC - Mass Data Capture'),
                        'MLPP' => __('MLPP - Ministry of Land and Physical Planning'),
                    ];

                $paymentStructureOptions = is_array($paymentStructures ?? null)
                    ? $paymentStructures
                    : [];

                $shiftDropdown = collect($attendanceShifts ?? [])
                    ->mapWithKeys(fn ($details, $code) => [
                        $code => $details['label'] ?? strtoupper($code),
                    ])
                    ->toArray();

                $selectedShift = old('shift_code', $user->shift_code);
                $autoDeactivateOldRaw = old('auto_deactivate');
                if ($autoDeactivateOldRaw !== null) {
                    $autoDeactivateInitial = (bool) ((int) $autoDeactivateOldRaw);
                } else {
                    $autoDeactivateInitial = (bool) ($user->auto_deactivate ?? 0);
                }
                $lastMdcAutoDeactivateInitial = $autoDeactivateInitial;
            @endphp

            <div class="p-6 overflow-y-auto max-h-[80vh]" x-data="{
                selectedDept: {{ json_encode(old('department_id', $user->department_id ?? '')) }},
                selectedDeptName: '',
                showAll: false,
                userTypeId: '',
                userTypeName: {{ json_encode(old('user_type', $user->type ?? $user->user_type ?? '')) }},
                userLevelName: {{ json_encode(old('user_level', $user->user_level ?? '')) }},
                selectedStaffTypeCategory: {{ json_encode(old('staff_type_category', $user->staff_type_category)) }},
                selectedStaffTypeCategoryLabel: '',
                autoDeactivateValue: {{ json_encode($autoDeactivateInitial) }},
                lastMdcAutoDeactivateValue: {{ json_encode($lastMdcAutoDeactivateInitial) }},
                pcAccessValue: {{ json_encode((bool) old('is_pc_access', $user->is_pc_access ?? 0)) }},
                lastMdcPcAccessValue: {{ json_encode((bool) old('is_pc_access', $user->is_pc_access ?? 0)) }},
                leaveStartDate: {{ json_encode(old('leave_start_date', optional($user->leave_start_date)->format('Y-m-d'))) }},
                leaveEndDate: {{ json_encode(old('leave_end_date', optional($user->leave_end_date)->format('Y-m-d'))) }},

                get leaveDurationDays() {
                    if (!this.leaveStartDate || !this.leaveEndDate) {
                        return null;
                    }
                    const start = new Date(this.leaveStartDate);
                    const end = new Date(this.leaveEndDate);
                    if (isNaN(start) || isNaN(end) || end < start) {
                        return null;
                    }
                    return Math.round((end - start) / 86400000) + 1;
                },

                init() {
                    this.$nextTick(() => {
                        this.$watch('autoDeactivateValue', (value) => {
                            if (this.canEditAdvancedFields) {
                                this.lastMdcAutoDeactivateValue = value;
                            }
                        });

                        this.$watch('pcAccessValue', (value) => {
                            if (this.canEditAdvancedFields) {
                                this.lastMdcPcAccessValue = value;
                            }
                        });

                        if (this.$refs.staffTypeCategory) {
                            this.onStaffTypeCategoryChange({ target: this.$refs.staffTypeCategory });
                        }
                    });
                },

                onStaffTypeCategoryChange(event) {
                    this.selectedStaffTypeCategory = event.target?.value ?? '';
                    this.selectedStaffTypeCategoryLabel = event.target?.selectedOptions?.[0]?.text ?? '';
                    this.updateAutoDeactivateForStaffType();
                },

                get canEditAdvancedFields() {
                    const value = (this.selectedStaffTypeCategory || '').toString().toLowerCase();
                    const label = (this.selectedStaffTypeCategoryLabel || '').toString().toLowerCase();
                    if (value) {
                        return value === 'mdc' || value.startsWith('mdc ');
                    }
                    return label.includes('mdc - mass data capture');
                },

                get shouldAutoEnableAccess() {
                    const value = (this.selectedStaffTypeCategory || '').toString().toLowerCase();
                    const label = (this.selectedStaffTypeCategoryLabel || '').toString().toLowerCase();
                    if (value) {
                        return value === 'mlpp' || value.startsWith('mlpp ') || value === 'mdcm' || value.startsWith('mdcm ');
                    }
                    return label.includes('mlpp - ministry of land and physical planning') || label.includes('mdcm - mdc management');
                },

                updateAutoDeactivateForStaffType() {
                    if (this.shouldAutoEnableAccess) {
                        this.autoDeactivateValue = true;
                        this.pcAccessValue = true;
                        return;
                    }

                    if (this.canEditAdvancedFields) {
                        this.autoDeactivateValue = this.lastMdcAutoDeactivateValue ?? true;
                        this.pcAccessValue = this.lastMdcPcAccessValue ?? (this.pcAccessValue ?? false);
                        return;
                    }
                },
                
                // Auto-determine user level based on user type (Step 3)
                autoSetUserLevel(userTypeName) {
                    // Clear previous level
                    this.userLevelName = '';
                    
                    // Auto-determine level based on user type
                    switch(userTypeName) {
                        case 'Management':
                            this.userLevelName = 'Highest';
                            break;
                        case 'Operations':
                            this.userLevelName = 'High';
                            break;
                        case 'System':
                            this.userLevelName = 'Highest';
                            break;
                        case 'User':
                            this.userLevelName = 'Lowest';
                            break;
                        case 'ALL':
                            this.userLevelName = 'Lowest';
                            break;
                        default:
                            this.userLevelName = '';
                    }
                    
                    console.log('Auto-set level:', this.userLevelName, 'for user type:', userTypeName);
                },
                
                checkAll() {
                    document.querySelectorAll('#roles_grid > div').forEach(el => {
                        const isVisible = el.style.display !== 'none' && !el.hasAttribute('x-show') || 
                                         (el.hasAttribute('x-show') && el.offsetParent !== null);
                        if (isVisible) {
                            const checkbox = el.querySelector('input[type=checkbox]');
                            if (checkbox) checkbox.checked = true;
                        }
                    });
                },
                
                uncheckAll() {
                    document.querySelectorAll('#roles_grid > div').forEach(el => {
                        const isVisible = el.style.display !== 'none' && !el.hasAttribute('x-show') || 
                                         (el.hasAttribute('x-show') && el.offsetParent !== null);
                        if (isVisible) {
                            const checkbox = el.querySelector('input[type=checkbox]');
                            if (checkbox) checkbox.checked = false;
                        }
                    });
                },
                
                showAllRoles() {
                    this.showAll = true;
                    this.selectedDept = '';
                },
                
                // Step 4: Display Available Roles based on Department + User Type + Level
                shouldShowRole(roleUserType, roleLevel, roleName, roleDeptId) {
                    // If showing all roles, show everything
                    if (this.showAll) {
                        return true;
                    }
                    
                    // Department filtering (Step 1)
                    if (this.selectedDept) {
                        const roleDepId = String(roleDeptId);
                        const selectedDepId = String(this.selectedDept);
                        
                        // Hide roles that belong to other departments (unless they're universal)
                        if (roleDepId !== 'null' && roleDepId !== '' && roleDepId !== 'undefined' && roleDepId !== selectedDepId) {
                            return false;
                        }
                    }
                    
                    // User Type and Level filtering (Steps 2 & 3)
                    if (this.userTypeName && this.userLevelName) {
                        // Always show ALL user_type roles
                        if (roleUserType === 'ALL') {
                            return true;
                        }
                        
                        // Show roles that match the selected user type and level
                        if (roleUserType === this.userTypeName && roleLevel === this.userLevelName) {
                            return true;
                        }
                        
                        // Hierarchical access: higher levels can access lower level roles
                        if (this.userTypeName === 'Management') {
                            // Management can access Operations and User roles
                            if (roleUserType === 'Operations' || roleUserType === 'User') {
                                return true;
                            }
                        }
                        
                        if (this.userTypeName === 'Operations') {
                            // Operations can access User roles
                            if (roleUserType === 'User') {
                                return true;
                            }
                        }
                        
                        if (this.userTypeName === 'System') {
                            // System can access all role types
                            return true;
                        }
                        
                        // If we reach here and user type/level are selected, hide roles that don't match
                        return false;
                    }
                    
                    // If only user type is selected (no level yet)
                    if (this.userTypeName && !this.userLevelName) {
                        // Always show ALL user_type roles
                        if (roleUserType === 'ALL') {
                            return true;
                        }
                        
                        // Show roles that match the selected user type (any level)
                        if (roleUserType === this.userTypeName) {
                            return true;
                        }
                        
                        // Apply hierarchical access rules
                        if (this.userTypeName === 'Management') {
                            if (roleUserType === 'Operations' || roleUserType === 'User') {
                                return true;
                            }
                        }
                        
                        if (this.userTypeName === 'Operations') {
                            if (roleUserType === 'User') {
                                return true;
                            }
                        }
                        
                        if (this.userTypeName === 'System') {
                            return true;
                        }
                        
                        return false;
                    }
                    
                    // If no user type/level selected, show all roles (filtered by department only)
                    return true;
                }
            }">
                <div class="flex flex-wrap -mx-2">
                    @if (\Auth::user()->type != 'super admin')
                        <div class="w-full px-3">
                            {{-- Basic Information Section --}}
                            <div class="mb-6 space-y-5 rounded-lg border border-gray-200 bg-gray-50 p-6">
                                <h4 class="text-md font-medium text-gray-800">Basic Information</h4>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        {{ Form::label('username', __('Username'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-id-badge"></i></span>
                                            {{ Form::text('username', null, [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm',
                                                'placeholder' => __('Enter Username'),
                                                'required' => 'required'
                                            ]) }}
                                        </div>
                                    </div>
                                    <div>
                                        {{ Form::label('password', __('New Password'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-lock"></i></span>
                                            {{ Form::password('password', [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm',
                                                'placeholder' => __('Leave blank to keep current password')
                                            ]) }}
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Leave blank to keep current password') }}</p>
                                    </div>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        {{ Form::label('first_name', __('First Name'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-user"></i></span>
                                            {{ Form::text('first_name', null, [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm',
                                                'placeholder' => __('Enter First Name'),
                                                'required' => 'required'
                                            ]) }}
                                        </div>
                                    </div>
                                    <div>
                                        {{ Form::label('last_name', __('Last Name'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-user-tag"></i></span>
                                            {{ Form::text('last_name', null, [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm',
                                                'placeholder' => __('Enter Last Name'),
                                                'required' => 'required'
                                            ]) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        {{ Form::label('phone_number', __('Phone Number'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-phone"></i></span>
                                            {{ Form::text('phone_number', null, [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm',
                                                'placeholder' => __('Enter Phone Number'),
                                                'required' => 'required'
                                            ]) }}
                                        </div>
                                    </div>
                                    <div>
                                        {{ Form::label('email', __('Email'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-envelope"></i></span>
                                            {{ Form::text('email', null, [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm',
                                                'placeholder' => __('Enter email'),
                                                'required' => 'required'
                                            ]) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    <div>
                                        {{ Form::label('staff_type_category', __('Staff Type Category'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-users"></i></span>
                                            {{ Form::select('staff_type_category', $staffTypeOptions, old('staff_type_category', $user->staff_type_category), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm bg-white',
                                                'placeholder' => __('Select staff type'),
                                                'required' => 'required',
                                                'x-ref' => 'staffTypeCategory',
                                                'x-on:change' => 'onStaffTypeCategoryChange($event)'
                                            ]) }}
                                        </div>
                                    </div>
                                    <div>
                                        {{ Form::label('work_station', __('Work Station'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-building"></i></span>
                                            {{ Form::select('work_station', $workStationOptions, old('work_station', $user->work_station), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm bg-white',
                                                'placeholder' => __('Select Work Station'),
                                                'disabled' => 'disabled',
                                                'x-bind:disabled' => '!canEditAdvancedFields'
                                            ]) }}
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Used for Activity Monitoring filters and reports.') }}</p>
                                    </div>
                                    <div>
                                        {{ Form::label('payment_structure_id', __('Standard Payment Structure'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-coins"></i></span>
                                            {{ Form::select('payment_structure_id', $paymentStructureOptions, old('payment_structure_id', $user->workstation_payment_structure_id), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm bg-white',
                                                'placeholder' => __('Select structure (optional)'),
                                                'disabled' => 'disabled',
                                                'x-bind:disabled' => '!canEditAdvancedFields'
                                            ]) }}
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Pick a preset rate by role/work days; leave empty for manual override.') }}</p>
                                    </div>
                                </div>
                                <div class="grid gap-4 md:grid-cols-3">
                                    <div>
                                        {{ Form::label('work_days_per_week', __('No of Work Days'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-calendar-check"></i></span>
                                            {{ Form::select('work_days_per_week', [
                                                2 => __('2 days'),
                                                5 => __('5 days'),
                                                7 => __('7 days'),
                                            ], old('work_days_per_week', $user->work_days_per_week), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm bg-white',
                                                'placeholder' => __('Select work days'),
                                                'disabled' => 'disabled',
                                                'x-bind:disabled' => '!canEditAdvancedFields'
                                            ]) }}
                                        </div>
                                    </div>
                                    <div>
                                        {{ Form::label('man_hours_per_day', __('No of Man Hours'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-clock"></i></span>
                                            {{ Form::select('man_hours_per_day', [
                                                4 => __('4 hours'),
                                                8 => __('8 hours'),
                                            ], old('man_hours_per_day', $user->man_hours_per_day), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm bg-white',
                                                'placeholder' => __('Select man hours'),
                                                'disabled' => 'disabled',
                                                'x-bind:disabled' => '!canEditAdvancedFields'
                                            ]) }}
                                        </div>
                                    </div>
                                    <div>
                                        {{ Form::label('base_salary_override', __('Base Salary Override'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-money-bill-wave"></i></span>
                                            {{ Form::number('base_salary_override', old('base_salary_override', $user->base_salary_override), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm',
                                                'placeholder' => __('Enter custom base salary (optional)'),
                                                'step' => '0.01',
                                                'min' => '0',
                                                'disabled' => 'disabled',
                                                'x-bind:disabled' => '!canEditAdvancedFields'
                                            ]) }}
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Overrides preset amount when provided.') }}</p>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2 mb-6">
                                    <div>
                                        {{ Form::label('shift_code', __('Assigned Shift'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-business-time"></i></span>
                                            {{ Form::select('shift_code', $shiftDropdown, $selectedShift, [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm bg-white',
                                                'placeholder' => __('Select shift'),
                                                'disabled' => 'disabled',
                                                'x-bind:disabled' => '!canEditAdvancedFields'
                                            ]) }}
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Determines attendance cut-offs and late thresholds.') }}</p>
                                    </div>
                                    <div>
                                        {{ Form::label('auto_deactivate', __('Auto Deactivation'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="flex items-start space-x-3">
                                            {{ Form::hidden('auto_deactivate', $autoDeactivateInitial ? 1 : 0, [
                                                'x-bind:value' => 'autoDeactivateValue ? 1 : 0'
                                            ]) }}
                                            <label class="relative inline-flex items-center cursor-pointer mt-1">
                                                {{ Form::checkbox('auto_deactivate', 1, $autoDeactivateInitial, [
                                                    'class' => 'sr-only peer',
                                                    'disabled' => 'disabled',
                                                    'x-bind:disabled' => '!canEditAdvancedFields',
                                                    'x-model' => 'autoDeactivateValue'
                                                ]) }}
                                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                            </label>
                                            <span class="text-sm font-medium text-gray-700 leading-5">{{ __('Suspend automatically after repeated absences') }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Disable to keep this user active regardless of absence limits.') }}</p>
                                    </div>
                                </div>

                                {{-- PC Access Toggle --}}
                                <div class="grid gap-4 md:grid-cols-2 mb-6">
                                    <div>
                                        {{ Form::label('is_pc_access', __('PC Access'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="flex items-start space-x-3">
                                            {{ Form::hidden('is_pc_access', old('is_pc_access', $user->is_pc_access ?? 0), [
                                                'x-bind:value' => 'pcAccessValue ? 1 : 0'
                                            ]) }}
                                            <label class="relative inline-flex items-center cursor-pointer mt-1">
                                                {{ Form::checkbox('is_pc_access', 1, old('is_pc_access', $user->is_pc_access) == 1, [
                                                    'class' => 'sr-only peer',
                                                    'disabled' => 'disabled',
                                                    'x-bind:disabled' => '!canEditAdvancedFields',
                                                    'x-model' => 'pcAccessValue'
                                                ]) }}
                                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                            </label>
                                            <span class="text-sm font-medium text-gray-700 leading-5">{{ __('User has PC/computer access') }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Toggle OFF for staff requiring manual attendance.') }}</p>
                                    </div>
                                </div>

                                {{-- Holiday/Leave & Deputy Redirection (MLPP staff) --}}
                                <div class="mb-4 p-4 rounded-lg border border-amber-200 bg-amber-50">
                                    <h5 class="text-sm font-semibold text-amber-800 mb-1">
                                        <i class="fas fa-umbrella-beach mr-1"></i>{{ __('Holiday/Leave & Deputy Redirection') }}
                                    </h5>
                                    <p class="text-xs text-amber-700 mb-3">{{ __('Applicable to MLPP staff — record leave status and who should receive their file/task redirects while away.') }}</p>

                                    <div class="mb-4">
                                        {{ Form::label('is_on_leave', __('Currently On Leave/Holiday'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="flex items-start space-x-3">
                                            {{ Form::checkbox('is_on_leave', 1, old('is_on_leave', $user->is_on_leave ?? false), [
                                                'class' => 'h-4 w-4 mt-1 rounded border-gray-300 text-amber-600 focus:ring-amber-500'
                                            ]) }}
                                            <span class="text-sm text-gray-700 leading-5">{{ __('Mark this staff member as currently on leave/holiday') }}</span>
                                        </div>
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div>
                                            {{ Form::label('leave_start_date', __('Leave Start Date'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                            {{ Form::date('leave_start_date', old('leave_start_date', optional($user->leave_start_date)->format('Y-m-d')), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 text-sm',
                                                'x-model' => 'leaveStartDate'
                                            ]) }}
                                        </div>
                                        <div>
                                            {{ Form::label('leave_end_date', __('Leave End Date'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                            {{ Form::date('leave_end_date', old('leave_end_date', optional($user->leave_end_date)->format('Y-m-d')), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 text-sm',
                                                'x-model' => 'leaveEndDate'
                                            ]) }}
                                        </div>
                                    </div>
                                    <div class="mb-4 mt-1 text-xs font-medium text-amber-700" x-show="leaveDurationDays !== null" x-cloak>
                                        <i class="fas fa-calendar-day mr-1"></i>
                                        <span x-text="leaveDurationDays"></span> <span x-text="leaveDurationDays === 1 ? 'day' : 'days'"></span> {{ __('of leave') }}
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div>
                                            {{ Form::label('deputy_user_id', __('Deputy (Redirect To)'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                            {{ Form::select('deputy_user_id', $deputyOptions ?? [], old('deputy_user_id', $user->deputy_user_id ?? null), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 text-sm bg-white',
                                                'placeholder' => __('Select deputy')
                                            ]) }}
                                            <p class="mt-1 text-xs text-gray-500">{{ __('Colleague who receives this user\'s file/task redirects while on leave.') }}</p>
                                        </div>
                                        <div>
                                            {{ Form::label('leave_reason', __('Leave Reason'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                            {{ Form::text('leave_reason', old('leave_reason', $user->leave_reason ?? null), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 text-sm',
                                                'placeholder' => __('e.g. Annual Leave, Sick Leave, Study Leave')
                                            ]) }}
                                        </div>
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2 mt-4">
                                        <div>
                                            {{ Form::label('out_of_office_from', __('Out of Office Date From'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                            {{ Form::date('out_of_office_from', old('out_of_office_from', optional($user->out_of_office_from)->format('Y-m-d')), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 text-sm'
                                            ]) }}
                                        </div>
                                        <div>
                                            {{ Form::label('out_of_office_to', __('Out of Office Date To'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                            {{ Form::date('out_of_office_to', old('out_of_office_to', optional($user->out_of_office_to)->format('Y-m-d')), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 text-sm'
                                            ]) }}
                                        </div>
                                    </div>
                                </div>

                            </div>


                            {{-- Hierarchical Role Management Section --}}
                            <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                <h4 class="text-md font-medium text-blue-800 mb-3">Hierarchical Role Management</h4>
                                <div class="text-sm text-blue-700 mb-4">
                                    Follow the steps below to assign user roles. Each step filters the next to ensure data consistency.
                                </div>
                                
                                <div class="flex flex-wrap -mx-2 mb-4">
                                    {{-- Step 1: Department Selection --}}
                                    <div class="w-full md:w-1/2 px-2 mb-4">
                                        <div>
                                            {{ Form::label('department_id', __('Step 1: Select Department'), ['class' => 'block text-sm font-medium text-blue-800 mb-1']) }}
                                            <div class="text-xs text-blue-600 mb-2">Choose the department to filter available roles</div>
                                            {{ Form::select('department_id', $departments, null, [
                                                'class' => 'w-full p-2 border border-blue-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500',
                                                'required' => 'required',
                                                'id' => 'department_id',
                                                'placeholder' => 'Select Department',
                                                '@change' => 'selectedDept = $event.target.value; selectedDeptName = $event.target.selectedOptions[0].text; showAll = !$event.target.value;'
                                            ]) }}
                                        </div>
                                    </div>
                                    {{-- Step 2: User Type Selection --}}
                                    <div class="w-full md:w-1/2 px-2 mb-4">
                                        <div>
                                            {{ Form::label('user_type', __('Step 2: Select User Type'), ['class' => 'block text-sm font-medium text-blue-800 mb-1']) }}
                                            <div class="text-xs text-blue-600 mb-2">User level will be automatically determined</div>
                                            <select name="user_type" id="user_type"
                                                class="w-full p-2 border border-blue-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
                                                x-init="$el.value = userTypeName"
                                                @change="userTypeName = $event.target.value; autoSetUserLevel(userTypeName);"
                                                required>
                                                <option value="">Select User Type</option>
                                                @php
                                                    $userTypes = ['Management', 'Operations', 'System', 'User', 'ALL'];
                                                @endphp
                                                @foreach($userTypes as $userType)
                                                    <option value="{{ $userType }}" {{ old('user_type', $user->type ?? $user->user_type) == $userType ? 'selected' : '' }}>{{ $userType }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                {{-- Step 3: Auto-populated User Level --}}
                                <div class="flex flex-wrap -mx-2 mb-4">
                                    <div class="w-full md:w-1/2 px-2 mb-4">
                                        <div>
                                            {{ Form::label('user_level', __('Step 3: User Level (Auto-determined)'), ['class' => 'block text-sm font-medium text-blue-800 mb-1']) }}
                                            <div class="text-xs text-blue-600 mb-2">Automatically set based on selected user type</div>
                                            <div x-show="!userTypeName">
                                                <input type="text" 
                                                    class="w-full p-2 border border-blue-300 rounded-md text-sm bg-gray-100"
                                                    value="Select User Type First"
                                                    readonly>
                                            </div>
                                            <div x-show="userTypeName">
                                                <input type="text" 
                                                    class="w-full p-2 border border-blue-300 rounded-md text-sm bg-green-50 text-green-800 font-medium"
                                                    x-bind:value="userLevelName || 'Determining...'"
                                                    readonly>
                                                <input type="hidden" name="user_level" x-bind:value="userLevelName">
                                            </div>
                                        </div>
                                    </div>
                                    {{-- User Type to Level Mapping Info --}}
                                    <div class="w-full md:w-1/2 px-2 mb-4">
                                        <div class="text-xs text-blue-700 bg-blue-100 p-3 rounded-md">
                                            <strong>Auto-Level Mapping:</strong><br>
                                            • Management → Highest<br>
                                            • Operations → High<br>
                                            • System → Highest<br>
                                            • User → Lowest<br>
                                            • ALL → Lowest
                                        </div>
                                    </div>
                                </div>

                                {{-- Officer Rank (Seniority) — used to prioritise file search requests --}}
                                <div class="flex flex-wrap -mx-2">
                                    <div class="w-full md:w-1/2 px-2 mb-2">
                                        <div>
                                            {{ Form::label('rank', __('Officer Rank (Seniority)'), ['class' => 'block text-sm font-medium text-blue-800 mb-1']) }}
                                            <div class="text-xs text-blue-600 mb-2">Designation used to prioritise this officer's file search requests — the most senior requester is honored first.</div>
                                            @php $currentRank = old('rank', $user->rank ?? ''); @endphp
                                            <select name="rank" id="rank"
                                                class="w-full p-2 border border-blue-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                                                <option value="">Select Rank (optional)</option>
                                                @foreach($ranks as $rankOption)
                                                    <option value="{{ $rankOption }}" {{ $currentRank === $rankOption ? 'selected' : '' }}>{{ $rankOption }}</option>
                                                @endforeach
                                                {{-- Preserve a previously-saved rank that isn't in the configured options --}}
                                                @if($currentRank && !in_array($currentRank, $ranks, true))
                                                    <option value="{{ $currentRank }}" selected>{{ $currentRank }} (current)</option>
                                                @endif
                                            </select>
                                        </div>
                                    </div>
                                    <div class="w-full md:w-1/2 px-2 mb-2">
                                        <div class="text-xs text-blue-700 bg-blue-100 p-3 rounded-md">
                                            <strong>Seniority (honored first):</strong><br>
                                            @foreach($ranks as $rankOption)
                                                • {{ $rankOption }}<br>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @php
                                $userActions = !empty($user->user_actions) ? explode(',', $user->user_actions) : [];
                            @endphp
                            
                            {{-- User Actions Section --}}
                            <div class="mb-6 p-6 rounded-lg border border-purple-200 bg-purple-50">
                                <h4 class="text-md font-medium text-purple-800 mb-3">
                                    <i class="fas fa-tasks mr-2"></i>User Actions (user_actions)
                                </h4>
                                <p class="text-xs text-purple-600 mb-4">Select the specific actions this user is permitted to perform.</p>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4" x-data="{ viewChecked: {{ in_array('view', $userActions) ? 'true' : 'false' }} }">
                                    <div class="flex items-center">
                                        <input type="checkbox" name="user_actions[]" value="create" id="action_create" 
                                            {{ in_array('create', $userActions) ? 'checked' : '' }}
                                            class="h-4 w-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                        <label for="action_create" class="ml-2 text-sm font-medium text-gray-700">Create</label>
                                    </div>
                                    <div class="flex items-center space-x-4">
                                        <div class="flex items-center">
                                            <input type="checkbox" name="user_actions[]" value="view" id="action_view" 
                                                class="h-4 w-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                                                x-model="viewChecked">
                                            <label for="action_view" class="ml-2 text-sm font-medium text-gray-700">View</label>
                                        </div>
                                        <div class="flex items-center" x-show="viewChecked" style="display: none;">
                                            <input type="checkbox" name="user_actions[]" value="view_all" id="action_view_all" 
                                                {{ in_array('view_all', $userActions) ? 'checked' : '' }}
                                                class="h-4 w-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                            <label for="action_view_all" class="ml-2 text-sm font-medium text-gray-700">View All</label>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="checkbox" name="user_actions[]" value="update" id="action_update" 
                                            {{ in_array('update', $userActions) ? 'checked' : '' }}
                                            class="h-4 w-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                        <label for="action_update" class="ml-2 text-sm font-medium text-gray-700">Update</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="checkbox" name="user_actions[]" value="delete" id="action_delete" 
                                            {{ in_array('delete', $userActions) ? 'checked' : '' }}
                                            class="h-4 w-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                        <label for="action_delete" class="ml-2 text-sm font-medium text-gray-700">Delete</label>
                                    </div>
                                </div>
                            </div>

                            {{-- Step 4: Available Roles --}}
                            <div class="mt-6" id="roles_container">
                                {{ Form::label('user_role', __('Step 4: Select Available Roles'), ['class' => 'block text-sm font-medium text-gray-700 mb-2']) }}
                                
                                <!-- Selection Summary -->
                                <div class="mb-3 p-3 bg-green-50 border border-green-200 rounded-md text-sm" x-show="userTypeName && userLevelName">
                                    <div class="font-medium text-green-800 mb-1">Selection Summary:</div>
                                    <div class="text-green-700">
                                        <span class="font-medium">Department:</span> <span x-text="selectedDeptName || 'All Departments'"></span> |
                                        <span class="font-medium">User Type:</span> <span x-text="userTypeName"></span> |
                                        <span class="font-medium">Level:</span> <span x-text="userLevelName"></span>
                                    </div>
                                    <div class="text-xs text-green-600 mt-1">
                                        <strong>Access Rules:</strong><br>
                                        <span x-show="userTypeName === 'Management'">• Can access Management, Operations, and User roles</span>
                                        <span x-show="userTypeName === 'Operations'">• Can access Operations and User roles</span>
                                        <span x-show="userTypeName === 'User'">• Can access User roles only</span>
                                        <span x-show="userTypeName === 'System'">• Can access all role types</span>
                                        <br>• ALL user_type roles are always visible
                                    </div>
                                </div>
                                
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                    <div class="mb-3 flex gap-2">
                                        <button type="button" @click="checkAll" class="text-xs py-1 px-2 rounded bg-green-500 text-white hover:bg-green-600">Check All Visible</button>
                                        <button type="button" @click="uncheckAll" class="text-xs py-1 px-2 rounded bg-red-500 text-white hover:bg-red-600">Uncheck All</button>
                                    </div>
                                    <div class="grid grid-cols-3 gap-3" id="roles_grid">
                                        @foreach ($userRoles as $role)
                                            <div class="flex items-start role-item"
                                                x-show="shouldShowRole('{{ $role->user_type ?? '' }}', '{{ $role->level ?? '' }}', {{ json_encode($role->name) }}, '{{ $role->department_id ?? 'null' }}')"
                                                data-dept-id="{{ $role->department_id ?? 'null' }}"
                                                data-user-type="{{ $role->user_type ?? '' }}"
                                                data-level="{{ $role->level ?? '' }}">
                                                <div class="flex items-center h-5">
                                                    <input type="checkbox" name="user_role[]" value="{{ $role->name }}"
                                                        {{ in_array($role->name, $userAssignedRoles ?? []) ? 'checked' : '' }}
                                                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 role-checkbox"
                                                        @if($role->name === 'Log a File') id="role_log_a_file_edit" @endif>
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label class="font-medium text-gray-700">{{ $role->name }}</label>
                                                    <small class="text-gray-500 block">{{ $role->user_type ?? 'N/A' }} - {{ $role->level ?? 'N/A' }}</small>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- DFR sub-permissions modal (triggered when Log a File is checked) --}}
                                @php
                                    $dfrPerms    = !empty($user->dfr_permissions)
                                        ? array_map('trim', explode(',', $user->dfr_permissions))
                                        : [];
                                    $hasLogAFile = in_array('Log a File', $userAssignedRoles ?? []);
                                @endphp
                                <div id="dfr-modal-edit" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4">
                                    {{-- Backdrop --}}
                                    <div id="dfr-modal-backdrop-edit" class="absolute inset-0 bg-black/50"></div>
                                    {{-- Dialog --}}
                                    <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden">
                                        {{-- Header --}}
                                        <div class="bg-gradient-to-r from-[#450a0a] via-[#6b1010] to-[#450a0a] px-5 py-4 flex items-center gap-3">
                                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20">
                                                <i class="fas fa-file-alt text-white text-sm"></i>
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-white">Digital File Request Permissions</p>
                                                <p class="text-xs text-red-200">Select what this user can do on /digital-request</p>
                                            </div>
                                            <button type="button" id="dfr-modal-close-edit" class="ml-auto text-red-200 hover:text-white transition">
                                                <i class="fas fa-times text-base"></i>
                                            </button>
                                        </div>
                                        {{-- Body --}}
                                        <div class="p-5 space-y-3">
                                            <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 bg-gray-50 hover:border-red-300 hover:bg-red-50 cursor-pointer transition">
                                                <input type="checkbox" name="dfr_permissions[]" value="view_requests" id="dfr_view_requests_edit"
                                                    {{ in_array('view_requests', $dfrPerms) ? 'checked' : '' }}
                                                    class="h-4 w-4 rounded border-gray-300 text-[#450a0a] focus:ring-red-400">
                                                <div>
                                                    <span class="text-sm font-semibold text-gray-800">View Request Page</span>
                                                    <span class="block text-xs text-gray-500">Can access /digital-request and see all requests</span>
                                                </div>
                                            </label>
                                            <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 bg-gray-50 hover:border-red-300 hover:bg-red-50 cursor-pointer transition">
                                                <input type="checkbox" name="dfr_permissions[]" value="approve_request" id="dfr_approve_request_edit"
                                                    {{ in_array('approve_request', $dfrPerms) ? 'checked' : '' }}
                                                    class="h-4 w-4 rounded border-gray-300 text-[#450a0a] focus:ring-red-400">
                                                <div>
                                                    <span class="text-sm font-semibold text-gray-800">Approve Request</span>
                                                    <span class="block text-xs text-gray-500">Can approve or reject file requests</span>
                                                </div>
                                            </label>

                                            {{-- File Request (FR) role --}}
                                            <div class="pt-2 mt-1 border-t border-gray-100">
                                                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">File Request (Mobile)</p>
                                                <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 bg-gray-50 hover:border-blue-300 hover:bg-blue-50 cursor-pointer transition">
                                                    <input type="checkbox" name="fr_permissions" value="SCB" id="fr_scb_edit"
                                                        {{ ($user->fr_permissions ?? '') === 'SCB' ? 'checked' : '' }}
                                                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-400">
                                                    <div>
                                                        <span class="text-sm font-semibold text-gray-800">SCB Monitor (File Searcher)</span>
                                                        <span class="block text-xs text-gray-500">Receives File Requests on the mobile app &amp; by email</span>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                        {{-- Footer --}}
                                        <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-end gap-2">
                                            <button type="button" id="dfr-modal-cancel-edit"
                                                class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                                Cancel
                                            </button>
                                            <button type="button" id="dfr-modal-done-edit"
                                                class="px-4 py-2 text-sm font-medium text-white bg-[#450a0a] hover:bg-[#5c0c0c] rounded-lg transition">
                                                Done
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <script>
                                (function () {
                                    function initDfrModalEdit() {
                                        var cb       = document.getElementById('role_log_a_file_edit');
                                        var modal    = document.getElementById('dfr-modal-edit');
                                        var backdrop = document.getElementById('dfr-modal-backdrop-edit');
                                        var btnDone  = document.getElementById('dfr-modal-done-edit');
                                        var btnCancel= document.getElementById('dfr-modal-cancel-edit');
                                        var btnClose = document.getElementById('dfr-modal-close-edit');
                                        if (!cb || !modal) return;

                                        function openModal()  { modal.classList.remove('hidden'); }
                                        function closeModal() { modal.classList.add('hidden'); }
                                        function cancelModal() {
                                            closeModal();
                                        }

                                        // On edit page: if user already has Log a File + dfr_permissions, don't auto-open
                                        cb.addEventListener('change', function() {
                                            if (cb.checked) openModal(); else cancelModal();
                                        });
                                        // Re-open modal when clicking the role label to edit permissions
                                        var editBtn = document.getElementById('dfr-edit-perms-btn');
                                        if (editBtn) editBtn.addEventListener('click', openModal);

                                        btnDone.addEventListener('click', closeModal);
                                        btnCancel.addEventListener('click', cancelModal);
                                        btnClose.addEventListener('click', closeModal);
                                        backdrop.addEventListener('click', closeModal);
                                    }
                                    if (document.readyState === 'loading') {
                                        document.addEventListener('DOMContentLoaded', initDfrModalEdit);
                                    } else {
                                        initDfrModalEdit();
                                    }
                                })();
                                </script>
                                {{-- Edit permissions link (shown when Log a File is already assigned) --}}
                                @if($hasLogAFile)
                                <div class="mt-2 flex items-center gap-2">
                                    <span class="text-xs text-gray-500">DFR permissions:
                                        @if(count($dfrPerms))
                                            <strong class="text-[#450a0a]">{{ implode(', ', $dfrPerms) }}</strong>
                                        @else
                                            <span class="text-amber-600">none set</span>
                                        @endif
                                    </span>
                                    <button type="button" id="dfr-edit-perms-btn"
                                        class="text-xs text-[#450a0a] underline hover:text-red-800">Edit permissions</button>
                                </div>
                                @endif
                                
                                <!-- Role Management Helper Buttons -->
                                <div class="mt-3 text-right">
                                    <button type="button" id="showAllRolesBtn" @click="showAllRoles()" 
                                        :class="{'bg-indigo-600 text-white': showAll, 'text-indigo-600 border border-indigo-600': !showAll}"
                                        class="text-sm py-1 px-2 rounded">
                                        Show All Roles
                                    </button>
                                </div>
                                
                                <!-- Filter Status Message -->
                                <div class="mt-2 text-sm" x-show="!showAll && (selectedDept || userTypeName)">
                                    <span class="text-green-600">✓ Hierarchical filters applied - showing relevant roles only</span>
                                </div>
                                <div class="mt-2 text-sm" x-show="showAll">
                                    <span class="text-orange-600">⚠ Showing all roles - hierarchical filtering disabled</span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            <div class="px-6 py-3 bg-gray-50 text-right">
                {{ Form::submit(__('Save Changes'), ['class' => 'inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500']) }}
                <button type="button" class="ml-2 inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" data-dismiss="modal">
                    {{ __('Cancel') }}
                </button>
            </div>
            {{ Form::close() }}
        </div>
    </div>
</div>
