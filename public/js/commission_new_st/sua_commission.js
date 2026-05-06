// SuA (Standalone Unit Application) JavaScript Module for Commission Interface

// SuA uses the shared applicant type change handler with 'sua_' prefix
// No need to redefine - the shared function handles this

// Allocation Information section removed - no longer needed

// Handle SuA land use change - PREVIEW ONLY (no automatic file generation)
function handleSuaLandUseChange(checkboxElement) {
    console.log('🎯 SuA Land use checkbox changed:', checkboxElement.value, 'checked:', checkboxElement.checked);
    
    // Handle checkbox selection (only allow one selection like radio button)
    const allCheckboxes = document.querySelectorAll('input[name="sua_selectedLandUse"]');
    const checkedBoxes = [];
    
    allCheckboxes.forEach(checkbox => {
        if (checkbox !== checkboxElement && checkboxElement.checked) {
            // Uncheck other checkboxes when one is selected
            checkbox.checked = false;
            checkbox.parentElement.classList.remove('selected');
        }
        
        if (checkbox.checked) {
            checkedBoxes.push(checkbox.value);
            checkbox.parentElement.classList.add('selected');
        } else {
            checkbox.parentElement.classList.remove('selected');
        }
    });
    
    // Update hidden input
    const selectedLandUse = checkedBoxes.length > 0 ? checkedBoxes[0] : '';
    const hiddenInput = document.getElementById('sua_land_use_hidden');
    if (hiddenInput) {
        hiddenInput.value = selectedLandUse;
    }
    
    console.log('🎯 Selected SuA land use:', selectedLandUse);
    
    if (selectedLandUse) {
        console.log('� Showing SuA file number preview for land use:', selectedLandUse);
        // Show preview only - DO NOT generate actual file numbers
        showSuaFileNumberPreview(selectedLandUse);
    } else {
        // Clear file numbers if no land use selected
        clearSuaFileNumbers();
    }
}

// Clear SuA file numbers
function clearSuaFileNumbers() {
    const primaryFileNoInput = document.getElementById('sua_primary_fileno');
    const suaFileNoInput = document.getElementById('sua_fileno');
    const mlsFileNoInput = document.getElementById('mls_fileno');
    
    if (primaryFileNoInput) primaryFileNoInput.value = '';
    if (suaFileNoInput) suaFileNoInput.value = '';
    if (mlsFileNoInput) mlsFileNoInput.value = '';
}

// Show SuA file number preview - REAL PREVIEW with actual serial numbers
function showSuaFileNumberPreview(landUse) {
    console.log('📋 Showing SuA file number preview for:', landUse);
    
    // Show loading state
    const primaryFileNoInput = document.getElementById('sua_primary_fileno');
    const mlsFileNoInput = document.getElementById('mls_fileno');
    const suaFileNoInput = document.getElementById('sua_fileno');
    
    if (primaryFileNoInput) primaryFileNoInput.value = 'Loading preview...';
    if (mlsFileNoInput) mlsFileNoInput.value = 'Loading preview...';
    if (suaFileNoInput) suaFileNoInput.value = 'Loading preview...';
    
    // Call preview API to get actual next file numbers
    fetch('/api/st-file-numbers/preview', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            land_use: landUse,
            type: 'SUA'
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('SuA Preview API response:', data);
        
        if (data.success && data.data) {
            const primaryFileNo = data.data.preview_np_fileno;
            const unitFileNo = data.data.preview_unit_fileno;
            const mlsFileNo = data.data.preview_mls_fileno;
            
            // Update SuA Primary FileNo field with clean preview (no placeholder text)
            if (primaryFileNoInput) {
                primaryFileNoInput.value = primaryFileNo;
                primaryFileNoInput.classList.remove('text-green-600');
                primaryFileNoInput.classList.add('text-blue-600');
            }
            
            // Update MLS FileNo field (same as primary)  
            if (mlsFileNoInput) {
                mlsFileNoInput.value = mlsFileNo;
                mlsFileNoInput.classList.remove('text-green-600');
                mlsFileNoInput.classList.add('text-blue-600');
            }
            
            // Update SuA Unit FileNo field
            if (suaFileNoInput) {
                suaFileNoInput.value = unitFileNo;
                suaFileNoInput.classList.remove('text-green-600');
                suaFileNoInput.classList.add('text-blue-600');
            }
            
            console.log('✅ SuA file number preview updated (clean display):');
            console.log('  - Primary FileNo:', primaryFileNo);
            console.log('  - MLS FileNo:', mlsFileNo);
            console.log('  - Unit FileNo:', unitFileNo);
        } else {
            throw new Error(data.message || 'Failed to get SuA preview');
        }
    })
    .catch(error => {
        console.error('Error getting SuA preview:', error);
        
        // Fallback to error message on error
        if (primaryFileNoInput) {
            primaryFileNoInput.value = 'Error loading preview';
            primaryFileNoInput.classList.add('text-red-600');
        }
        if (mlsFileNoInput) {
            mlsFileNoInput.value = 'Error loading preview';
            mlsFileNoInput.classList.add('text-red-600');
        }
        if (suaFileNoInput) {
            suaFileNoInput.value = 'Error loading preview';
            suaFileNoInput.classList.add('text-red-600');
        }
    });
}

/**
 * Collect SuA applicant data from the form
 */
function collectSuAApplicantData() {
    // Determine which applicant type is selected
    const individualRadio = document.querySelector('input[name="sua_applicant_type"][value="Individual"]');
    const corporateRadio = document.querySelector('input[name="sua_applicant_type"][value="Corporate"]');
    const multipleRadio = document.querySelector('input[name="sua_applicant_type"][value="Multiple"]');

    let applicantType = null;
    if (individualRadio && individualRadio.checked) {
        applicantType = 'individual';
    } else if (corporateRadio && corporateRadio.checked) {
        applicantType = 'corporate';
    } else if (multipleRadio && multipleRadio.checked) {
        applicantType = 'multiple';
    }

    if (!applicantType) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Information',
            text: 'Please select an applicant type',
            confirmButtonColor: '#f59e0b'
        });
        return null;
    }

    const data = {
        applicant_type: applicantType
    };

    // Collect data based on applicant type
    if (applicantType === 'individual') {
        data.applicant_title = document.getElementById('sua_title')?.value || '';
        data.first_name = document.getElementById('sua_first_name')?.value || '';
        data.middle_name = document.getElementById('sua_middle_name')?.value || '';
        data.surname = document.getElementById('sua_last_name')?.value || '';

        // Validate required fields for individual
        if (!data.first_name || !data.surname) {
            console.log('Individual validation failed:', { first_name: data.first_name, surname: data.surname });
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please fill in first name and last name for individual applicant',
                confirmButtonColor: '#f59e0b'
            });
            return null;
        }
    } else if (applicantType === 'corporate') {
        data.corporate_name = document.getElementById('sua_corporate_name')?.value || '';
        data.rc_number = document.getElementById('sua_rc_number')?.value || '';

        // Validate required fields for corporate
        if (!data.corporate_name) {
            console.log('Corporate validation failed:', { corporate_name: data.corporate_name });
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please fill in corporate name for corporate applicant',
                confirmButtonColor: '#f59e0b'
            });
            return null;
        }
    } else if (applicantType === 'multiple') {
        // For multiple applicants, we need at least the primary applicant info
        data.first_name = document.getElementById('sua_owner_first_name')?.value || '';
        data.middle_name = document.getElementById('sua_owner_middle_name')?.value || '';  
        data.surname = document.getElementById('sua_owner_last_name')?.value || '';

        // Validate required fields for multiple
        if (!data.first_name || !data.surname) {
            console.log('Multiple validation failed:', { first_name: data.first_name, surname: data.surname });
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please fill in first name and last name for primary owner',
                confirmButtonColor: '#f59e0b'
            });
            return null;
        }
    }

    console.log('Collected SuA applicant data:', data);
    return data;
}

// Generate SuA file numbers
async function generateSuaFileNumbers() {
    console.log('Generating SuA file numbers...');
    
    // Get the selected land use from the hidden input (populated by checkbox handler)
    const landUseHidden = document.getElementById('sua_land_use_hidden');
    const landUse = landUseHidden?.value;
    
    if (!landUse) {
        Swal.fire({
            icon: 'warning',
            title: 'Land Use Required',
            text: 'Please select a land use before commissioning SuA file numbers.',
            confirmButtonColor: '#f59e0b'
        });
        return;
    }

    // Collect applicant data
    const applicantData = collectSuAApplicantData();
    if (!applicantData) {
        return; // Error already shown in collection function
    }
    
    // Show loading state
    const button = document.querySelector('button[onclick="generateSuaFileNumbers()"]');
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i data-lucide="loader-2" class="inline-block h-5 w-5 mr-2 animate-spin"></i>Generating...';
    
    try {
        // Get application type (required field)
        const applicationTypeRadio = document.querySelector('input[name="application_type"]:checked');
        const applicationType = applicationTypeRadio ? applicationTypeRadio.value : '';
        
        if (!applicationType) {
            Swal.fire({
                icon: 'warning',
                title: 'Application Type Required',
                text: 'Please select an application type (Direct Allocation or Conversion) before commissioning.',
                confirmButtonColor: '#f59e0b'
            });
            button.disabled = false;
            button.innerHTML = originalText;
            return;
        }
        
        // Prepare request data
        const requestData = {
            land_use: landUse.toUpperCase(),
            application_type: applicationType, // REQUIRED: Application Type
            ...applicantData,
            commissioned_by: document.getElementById('sua_commissioned_by')?.value || '',
            commissioned_date: document.getElementById('sua_commissioned_date')?.value || ''
        };

        console.log('SuA Request payload:', requestData);

        // Make API call to commission SuA file number
        const response = await fetch('/commission-new-st/commission-sua', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(requestData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            const fileNumber = result.suaFileNumber || result.data.sua_file_number;
            
            // Update the preview field
            const previewField = document.getElementById('sua_next_file_no');
            if (previewField) {
                previewField.textContent = fileNumber;
            }
            
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: `SuA file number ${fileNumber} commissioned successfully!`,
                confirmButtonColor: '#10b981'
            });
            
            // Update button state
            button.innerHTML = '<i data-lucide="check" class="inline-block h-5 w-5 mr-2"></i>SuA File Number Generated';
            button.classList.add('bg-gray-400', 'cursor-not-allowed');
            button.classList.remove('bg-blue-500');
            
        } else {
            console.error('SuA Commission failed:', result);
            
            if (result.errors) {
                const errorMessages = Object.values(result.errors).flat();
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: errorMessages.join('\n'),
                    confirmButtonColor: '#ef4444'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.message || 'Failed to commission SuA file number',
                    confirmButtonColor: '#ef4444'
                });
            }
            
            // Reset button
            button.disabled = false;
            button.innerHTML = originalText;
        }
        
    } catch (error) {
        console.error('Error commissioning SuA file number:', error);
        
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Network error occurred while commissioning',
            confirmButtonColor: '#ef4444'
        });
        
        // Reset button
        button.disabled = false;
        button.innerHTML = originalText;
    }
    
    // Reset button after a delay
    setTimeout(() => {
        button.disabled = false;
        button.innerHTML = originalText;
        // Re-initialize Lucide icons
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }, 2000);
}

// SuA Primary File Number Generation (adapted from sub_application.blade.php)
function generateSUAPrimaryFileNo(landUse) {
    console.log('🚀 Starting SuA file number generation for:', landUse);
    
    const landUseCode = {
        'Commercial': 'COM',
        'Industrial': 'IND',
        'Residential': 'RES',
        'Mixed-Use': 'MIX'
    }[landUse] || 'RES';
    
    console.log('📋 Land use code:', landUseCode);
    
    const currentYear = new Date().getFullYear();
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]') || 
                     document.querySelector('input[name="_token"]');
    
    console.log('🔒 CSRF Token found:', !!csrfToken);
    
    const apiUrl = `/api/st-file-numbers/reserve-sua`;
    console.log('🌐 API URL:', apiUrl);
    
    // Prepare request data
    const requestData = {
        land_use: landUse,
        applicant_type: 'Individual', // Default, will be updated when form is filled
        applicant_title: null,
        first_name: null,
        surname: null,
        corporate_name: null,
        rc_number: null,
        multiple_owners_names: null
    };
    
    // Fetch next file number from server with proper headers
    fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken ? csrfToken.content || csrfToken.value : '',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify(requestData)
    })
        .then(response => {
            console.log('📡 Response status:', response.status);
            console.log('📡 Response ok:', response.ok);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('📊 API Response data:', data);
            
            if (data.success) {
                const primaryFileNoInput = document.getElementById('sua_primary_fileno');
                const suaFileNoInput = document.getElementById('sua_fileno');
                const mlsFileNoInput = document.getElementById('mls_fileno');
                
                console.log('🔍 Found input fields:', {
                    primary: !!primaryFileNoInput,
                    sua: !!suaFileNoInput,
                    mls: !!mlsFileNoInput
                });
                
                // Use the new API response structure
                if (primaryFileNoInput && data.data && data.data.np_fileno) {
                    primaryFileNoInput.value = data.data.np_fileno;
                    console.log('✅ Set primary file no:', data.data.np_fileno);
                }
                if (suaFileNoInput && data.data && data.data.unit_fileno) {
                    suaFileNoInput.value = data.data.unit_fileno;
                    console.log('✅ Set SuA file no:', data.data.unit_fileno);
                }
                if (mlsFileNoInput && data.data && data.data.mls_fileno) {
                    mlsFileNoInput.value = data.data.mls_fileno;
                    console.log('✅ Set MLS file no:', data.data.mls_fileno);
                }
                
                console.log('✅ SuA file numbers generated successfully:', data);
                
                // Show success message
                showSuccessMessage('SuA file numbers generated successfully!');
            } else {
                console.error('❌ API returned success=false:', data.message || 'Unknown error');
                throw new Error(data.message || 'API returned success=false');
            }
        })
        .catch((error) => {
            console.error('❌ Failed to fetch SuA file numbers:', error);
            console.error('❌ Error details:', {
                message: error.message,
                stack: error.stack
            });
            
            // Show error message
            showErrorMessage('Error generating SuA file numbers: ' + error.message);
            
            // Fallback: Generate client-side numbers for demonstration
            console.log('🔄 Using fallback file number generation');
            const serial = Math.floor(Math.random() * 1000) + 1;
            const primaryFileNo = `ST-${landUseCode}-${currentYear}-${String(serial).padStart(4, '0')}`;
            
            const primaryFileNoInput = document.getElementById('sua_primary_fileno');
            const suaFileNoInput = document.getElementById('sua_fileno');
            const mlsFileNoInput = document.getElementById('mls_fileno');
            
            if (primaryFileNoInput) {
                primaryFileNoInput.value = primaryFileNo;
                console.log('🔄 Fallback primary file no:', primaryFileNo);
            }
            if (mlsFileNoInput) {
                mlsFileNoInput.value = primaryFileNo; // Same as primary for MLS
                console.log('🔄 Fallback MLS file no:', primaryFileNo);
            }
            if (suaFileNoInput) {
                suaFileNoInput.value = primaryFileNo + '-U001'; // Unit specific
            }
            
            showErrorMessage('Using fallback file number generation. Please check server connection.');
        });
}

// Utility functions
function showSuccessMessage(message) {
    // Create a toast notification
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-all duration-300';
    toast.innerHTML = `
        <div class="flex items-center gap-2">
            <i data-lucide="check-circle" class="h-5 w-5"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Initialize Lucide icons
    if (window.lucide) {
        window.lucide.createIcons();
    }
    
    // Remove after 3 seconds
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

function showErrorMessage(message) {
    // Create a toast notification
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-all duration-300';
    toast.innerHTML = `
        <div class="flex items-center gap-2">
            <i data-lucide="alert-circle" class="h-5 w-5"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Initialize Lucide icons
    if (window.lucide) {
        window.lucide.createIcons();
    }
    
    // Remove after 5 seconds
    setTimeout(() => {
        toast.remove();
    }, 5000);
}

// PUA File Number Generation Function
function generatePuaFileNumber() {
    console.log('Generating PUA file number...');
    
    // Get the parent file number from the input
    const parentFileNoInput = document.getElementById('pua_parent_fileno');
    const parentFileNo = parentFileNoInput?.value;
    
    if (!parentFileNo) {
        showErrorMessage('Please enter a parent file number before generating PUA file number.');
        return;
    }
    
    // Validate parent file number format (ST-XXX-YYYY-N)
    const fileNoPattern = /^ST-[A-Z]+-\d{4}-\d+$/;
    if (!fileNoPattern.test(parentFileNo)) {
        showErrorMessage('Invalid parent file number format. Expected: ST-XXX-YYYY-N');
        return;
    }
    
    // Show loading state
    const button = document.querySelector('button[onclick="generatePuaFileNumber()"]');
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i data-lucide="loader-2" class="inline-block h-5 w-5 mr-2 animate-spin"></i>Generating...';
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]') || 
                     document.querySelector('input[name="_token"]');
    
    const apiUrl = `/commission-new-st/pua-next-fileno?parent_file_number=${encodeURIComponent(parentFileNo)}`;
    console.log('🌐 PUA API URL:', apiUrl);
    
    // Fetch PUA file number from server
    fetch(apiUrl, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken ? csrfToken.content || csrfToken.value : '',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
        .then(response => {
            console.log('📡 PUA Response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('📊 PUA API Response data:', data);
            
            if (data.success) {
                const npFileNoInput = document.getElementById('pua_np_fileno');
                const unitFileNoInput = document.getElementById('pua_unit_fileno');
                
                console.log('🔍 Found PUA input fields:', {
                    npFileno: !!npFileNoInput,
                    unitFileno: !!unitFileNoInput
                });
                
                if (npFileNoInput && data.npFileNo) {
                    npFileNoInput.value = data.npFileNo;
                    console.log('✅ Set NP file no:', data.npFileNo);
                }
                if (unitFileNoInput && data.unitFileNo) {
                    unitFileNoInput.value = data.unitFileNo;
                    console.log('✅ Set Unit file no:', data.unitFileNo);
                }
                
                console.log('✅ PUA file number generated successfully:', data);
                
                // Show success message
                showSuccessMessage('PUA file number generated successfully!');
            } else {
                console.error('❌ PUA API returned success=false:', data.message || 'Unknown error');
                throw new Error(data.message || 'API returned success=false');
            }
        })
        .catch((error) => {
            console.error('❌ Failed to fetch PUA file number:', error);
            
            // Show error message
            showErrorMessage('Error generating PUA file number: ' + error.message);
            
            // Fallback: Generate client-side number for demonstration
            console.log('🔄 Using fallback PUA file number generation');
            const unitSerial = Math.floor(Math.random() * 100) + 1;
            const unitFileNo = `${parentFileNo}-${String(unitSerial).padStart(3, '0')}`;
            
            const npFileNoInput = document.getElementById('pua_np_fileno');
            const unitFileNoInput = document.getElementById('pua_unit_fileno');
            
            if (npFileNoInput) {
                npFileNoInput.value = parentFileNo;
                console.log('🔄 Fallback NP file no:', parentFileNo);
            }
            if (unitFileNoInput) {
                unitFileNoInput.value = unitFileNo;
                console.log('🔄 Fallback Unit file no:', unitFileNo);
            }
            
            showErrorMessage('Using fallback file number generation. Please check server connection.');
        })
        .finally(() => {
            // Reset button
            setTimeout(() => {
                button.disabled = false;
                button.innerHTML = originalText;
                // Re-initialize Lucide icons
                if (window.lucide) {
                    window.lucide.createIcons();
                }
            }, 1000);
        });
}

/**
 * Handle SuA Application Type Change
 */
function handleSuaApplicationTypeChange(radioElement) {
    console.log('SuA Application Type changed to:', radioElement.value);
    
    // Remove 'selected' class from all application type options
    document.querySelectorAll('.sua-application-type-option').forEach(option => {
        option.classList.remove('selected');
    });
    
    // Add 'selected' class to parent label
    const label = radioElement.closest('.sua-application-type-option');
    if (label) {
        label.classList.add('selected');
    }
    
    console.log('✅ SuA Application Type set to:', radioElement.value);
}

// Make functions globally available  
window.handleSuaLandUseChange = handleSuaLandUseChange;
window.handleSuaApplicationTypeChange = handleSuaApplicationTypeChange;
window.generateSuaFileNumbers = generateSuaFileNumbers;
window.generateSUAPrimaryFileNo = generateSUAPrimaryFileNo;
window.generatePuaFileNumber = generatePuaFileNumber;
window.clearSuaFileNumbers = clearSuaFileNumbers;

console.log('🎉 SuA JavaScript module loaded successfully');
console.log('🔧 Available SuA and PuA functions:', {
    handleSuaLandUseChange: typeof window.handleSuaLandUseChange,
    handleSuaApplicationTypeChange: typeof window.handleSuaApplicationTypeChange,
    generateSuaFileNumbers: typeof window.generateSuaFileNumbers,
    generateSUAPrimaryFileNo: typeof window.generateSUAPrimaryFileNo,
    generatePuaFileNumber: typeof window.generatePuaFileNumber,
    clearSuaFileNumbers: typeof window.clearSuaFileNumbers
});