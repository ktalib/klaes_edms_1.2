@extends('layouts.app')
@section('page-title')
    {{ $PageTitle }}
@endsection

@section('content')
    @php
        $landOfficerSignature = optional(\App\Models\LandOfficer::where('user_id', auth()->id())->first())->signature_file;
    @endphp
  <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">
        <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">My Profile</h1>
                <button id="editProfileBtn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 transition-all duration-200 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                    </svg>
                    Update Profile
                </button>
            </div>
            
            <div class="flex flex-col md:flex-row gap-8">
                <!-- Profile Image Section -->
                <div class="flex flex-col items-center">
                    <div class="w-48 h-48 rounded-full overflow-hidden border-4 border-blue-100 shadow-md mb-4">
                        @if(auth()->user()->profile)
                            <img src="{{ asset('storage/app/public/'.auth()->user()->profile) }}" alt="Profile" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center bg-blue-50 text-blue-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        @endif
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">{{ auth()->user()->name }}</h2>
                    @if($landOfficerSignature)
                        <div class="mt-4 text-center">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Lands 12 Signature</p>
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($landOfficerSignature) }}" alt="Signature"
                                class="h-12 object-contain mx-auto border border-gray-200 rounded-md bg-white px-2 py-1">
                        </div>
                    @endif
                    {{-- <span class="px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 mt-2">
                        {{ auth()->user()->type ?? auth()->user()->role ?? 'User' }}
                    </span> --}}
                </div>
                
                <!-- Profile Info Section -->
                <div class="flex-1">
                    <div class="bg-gray-50 rounded-lg p-5 shadow-inner">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b pb-2">Personal Information</h3>
                        
                        <div class="grid md:grid-cols-2 gap-x-8 gap-y-4">
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">First Name</h4>
                                <p class="mt-1 text-base">{{ auth()->user()->first_name ?? auth()->user()->name }}</p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Last Name</h4>
                                <p class="mt-1 text-base">{{ auth()->user()->last_name ?? '' }}</p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Email</h4>
                                <p class="mt-1 text-base">{{ auth()->user()->email }}</p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Phone Number</h4>
                                <p class="mt-1 text-base">{{ auth()->user()->phone_number ?? 'Not provided' }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-5 shadow-inner mt-6">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b pb-2">Account Information</h3>
                        
                        <div class="grid md:grid-cols-2 gap-x-8 gap-y-4">
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Account Created Since</h4>
                                <p class="mt-1 text-base">{{ auth()->user()->created_at->format('F d, Y') }}</p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Last Updated</h4>
                                <p class="mt-1 text-base">{{ auth()->user()->updated_at->format('F d, Y') }}</p>
                            </div>
                            <div class="md:col-span-2">
                                <h4 class="text-sm font-medium text-gray-500">Work Station</h4>
                                <p class="mt-1 text-base">{{ auth()->user()->work_station ?? 'Not assigned' }}</p>
                            </div>
                            {{-- <div>
                                <h4 class="text-sm font-medium text-gray-500">Role</h4>
                                <p class="mt-1 text-base">{{ auth()->user()->role ?? 'User' }}</p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Type</h4>
                                <p class="mt-1 text-base">{{ auth()->user()->type ?? 'Standard' }}</p>
                            </div> --}}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
      @include('admin.footer')
  </div>

  <!-- Enhanced Profile Update Modal -->
  <div id="profileModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4 overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-800 border-b flex justify-between items-center">
            <h3 class="text-xl font-medium text-white">Update Your Profile</h3>
            <button id="closeModal" class="text-white hover:text-gray-200 transition-colors">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="p-6">
            @csrf
            @method('PUT')
            
            <div class="flex flex-col md:flex-row gap-6">
                <!-- Profile Image Upload Section -->
                <div class="w-full md:w-1/3">
                    <div class="text-center">
                        <div class="mx-auto w-32 h-32 mb-4 rounded-full overflow-hidden border-4 border-blue-100 shadow-md relative group">
                            <div id="profilePreview" class="w-full h-full bg-blue-50 flex items-center justify-center">
                                @if(auth()->user()->profile)
                                    <img src="{{ asset('storage/app/public/'.auth()->user()->profile) }}" alt="Profile" class="w-full h-full object-cover">
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                    </svg>
                                @endif
                            </div>
                            <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                <span class="text-white text-sm">Change Photo</span>
                            </div>
                        </div>
                        
                        <label for="profile" class="cursor-pointer">
                            <span class="px-4 py-2 bg-blue-100 text-blue-600 rounded-full text-sm font-medium hover:bg-blue-200 transition-all duration-200 inline-flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 3H8.828a2 2 0 00-1.414.586L6.293 4.707A1 1 0 015.586 5H4zm6 9a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                                </svg>
                                Upload Photo
                            </span>
                            <input type="file" name="profile" id="profile" class="hidden" accept="image/*" onchange="previewImage(this)">
                        </label>

                        <label for="signature_file" class="cursor-pointer mt-3 inline-block">
                            <span class="px-4 py-2 bg-emerald-100 text-emerald-700 rounded-full text-sm font-medium hover:bg-emerald-200 transition-all duration-200 inline-flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M4 3a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 001-1V4a1 1 0 00-1-1H4zm1 3h10v2H5V6zm0 4h7v2H5v-2z" />
                                </svg>
                                Upload Signature
                            </span>
                            <input type="file" name="signature_file" id="signature_file" class="hidden" accept="image/png,image/jpeg" onchange="previewSignature(this)">
                        </label>
                        <p class="text-[11px] text-gray-500 mt-2">Used for Lands 12 after successful verification.</p>

                        <div id="signaturePreviewWrap" class="mt-3 {{ $landOfficerSignature ? '' : 'hidden' }}">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Signature Preview</p>
                            <div class="border border-gray-200 rounded-md bg-white p-2">
                                <img id="signaturePreview"
                                    src="{{ $landOfficerSignature ? \Illuminate\Support\Facades\Storage::url($landOfficerSignature) : '' }}"
                                    alt="Signature Preview"
                                    class="h-12 object-contain mx-auto">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Form Fields Section -->
                <div class="w-full md:w-2/3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                            <input type="text" name="first_name" id="first_name" value="{{ auth()->user()->first_name ?? '' }}"  class="w-full border rounded p-1 text-xs">
                        </div>
                        
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                            <input type="text" name="last_name" id="last_name" value="{{ auth()->user()->last_name ?? '' }}"  class="w-full border rounded p-1 text-xs">
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" name="email" id="email" value="{{ auth()->user()->email }}"  class="w-full border rounded p-1 text-xs">
                        </div>
                        
                        <div>
                            <label for="phone_number" class="block text-sm font-medium text-gray-700">Phone Number</label>
                            <input type="tel" name="phone_number" id="phone_number" value="{{ auth()->user()->phone_number ?? '' }}"  class="w-full border rounded p-1 text-xs">
                        </div>
                        <div class="md:col-span-2">
                            @php($selectedWorkStation = old('work_station', auth()->user()->work_station))
                            <label for="work_station" class="block text-sm font-medium text-gray-700">
                                Work Station @if($canEditWorkStation)<span class="text-red-500">*</span>@endif
                            </label>
                            <select name="work_station" id="work_station" class="w-full border rounded p-1 text-xs bg-white" @if(!$canEditWorkStation) disabled @endif @if($canEditWorkStation) required @endif>
                                <option value="">Select your work station</option>
                                @foreach($workStationOptions as $value => $label)
                                    <option value="{{ $value }}" {{ $selectedWorkStation === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs mt-1 {{ $canEditWorkStation ? 'text-gray-500' : 'text-amber-600' }}">
                                @if($canEditWorkStation)
                                    This one-time update exists for legacy accounts. Please confirm the correct workspace before saving.
                                @else
                                    Workspace locked to <span class="font-semibold">{{ auth()->user()->work_station }}</span>. Contact an administrator if a change is required.
                                @endif
                            </p>
                        </div>
                         
                        
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">New Password</label>
                            <input type="password" name="password" id="password" placeholder="Leave blank to keep current"  class="w-full border rounded p-1 text-xs">
                        </div>
                        
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation"  class="w-full border rounded p-1 text-xs">
                        </div>
                        
                        
                       
                    </div>
                        <p class="text-sm text-gray-500 italic">Leave the password fields blank if you do not wish to change your password.</p>

                </div>
            </div>
            
            <div class="mt-6 flex justify-end space-x-3 border-t pt-4">
                <button type="button" id="cancelBtn" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    Update Profile
                </button>
            </div>
        </form>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('profileModal');
        const editBtn = document.getElementById('editProfileBtn');
        const closeBtn = document.getElementById('closeModal');
        const cancelBtn = document.getElementById('cancelBtn');
        
        editBtn.addEventListener('click', function() {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
        
        function closeModal() {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
        
        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);
        
        // Close modal when clicking outside of it
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    });
    
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            
            reader.onload = function(e) {
                const preview = document.getElementById('profilePreview');
                preview.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
            }
            
            reader.readAsDataURL(input.files[0]);
        }
    }

    function previewSignature(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();

            reader.onload = function(e) {
                const wrap = document.getElementById('signaturePreviewWrap');
                const preview = document.getElementById('signaturePreview');
                if (wrap) wrap.classList.remove('hidden');
                if (preview) preview.src = e.target.result;
            }

            reader.readAsDataURL(input.files[0]);
        }
    }
  </script>
@endsection
