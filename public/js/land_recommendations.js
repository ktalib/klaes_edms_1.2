document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('land-recommendation-form');
    const pickBtn = document.getElementById('select-fileno-btn');
    const fileNoInput = document.getElementById('file_number');
    const applicantInput = document.getElementById('applicant_name');
    const locationInput = document.getElementById('location');
    const lgaInput = document.getElementById('lga');
    const plotInput = document.getElementById('plot_number');
    const layoutPlanInput = document.getElementById('layout_plan_no');
    const trackingInput = document.getElementById('tracking_id');
    const submitBtn = form?.querySelector('button[type="submit"]');

    // Recommendation Type Toggle
    const typeRadios = document.querySelectorAll('input[name="type"]');
    const conversionSection = document.getElementById('conversion-fields-section');

    typeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'Conversion') {
                conversionSection?.classList.remove('hidden');
            } else {
                conversionSection?.classList.add('hidden');
            }
        });
    });

    // Initialize Global File Number Modal
    if (window.GlobalFileNoModal) {
        window.GlobalFileNoModal.init();

        if (pickBtn) {
            pickBtn.addEventListener('click', () => {
                window.GlobalFileNoModal.open({
                    targetFields: ['#file_number'],
                    callback: (data) => {
                        console.log('File selected:', data);
                        if (data.record) {
                            const record = data.record;
                            
                            // Auto-populate fields
                            if (applicantInput) applicantInput.value = record.file_name || record.FileName || '';
                            if (locationInput) locationInput.value = record.location || '';
                            if (lgaInput) lgaInput.value = record.lga || '';
                            if (plotInput) plotInput.value = record.plot_no || '';
                            if (layoutPlanInput) layoutPlanInput.value = record.layout_plan_no || '';
                            if (trackingInput) trackingInput.value = record.tracking_id || '';

                            // Try to match Land Use
                            if (landUseSelect && record.land_use) {
                                Array.from(landUseSelect.options).forEach(option => {
                                    if (option.text.trim().toLowerCase() === record.land_use.trim().toLowerCase()) {
                                        landUseSelect.value = option.value;
                                        landUseSelect.dispatchEvent(new Event('change'));
                                    }
                                });
                            }

                            // Trigger change event for any listeners
                            [applicantInput, locationInput, lgaInput, plotInput, layoutPlanInput, trackingInput].forEach(input => {
                                if (input) input.dispatchEvent(new Event('change'));
                            });

                            // Auto-detect term based on Land Use
                            const termInput = document.querySelector('input[name="term"]');
                            if (termInput && record.land_use) {
                                const lu = record.land_use.toUpperCase().trim();
                                if (lu.includes('RESIDENTIAL') || lu.includes('AGRICULTURAL') || lu.includes('AGRICULTURE')) {
                                    termInput.value = '99';
                                } else if (lu.includes('COMMERCIAL') || lu.includes('INDUSTRIAL')) {
                                    termInput.value = '40';
                                }
                            }

                            // Clean up file_number if it has (Block)
                            if (fileNoInput && fileNoInput.value.includes('(Block)')) {
                                fileNoInput.value = fileNoInput.value.replace(/\s*\(Block\)/, '').trim();
                            }
                        }
                    }
                });
            });
        }
    }

    // Handle Form Submission via AJAX (optional, but requested "api js")
    if (form) {
        form.addEventListener('submit', function (e) {
            // Check if we want to use AJAX or standard POST
            // For now, let's keep it standard POST but add a loader if possible
            // Or if really wanted "api js", we do fetch.
            
            if (!fileNoInput.value) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'File Number Required',
                    text: 'Please select a file number before saving.'
                });
                return;
            }

            if (landUseSelect && !landUseSelect.value) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Land Use Required',
                    text: 'Please select a land use category.'
                });
                return;
            }

            if (purposeSelect && !purposeSelect.value) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Purpose Clause Required',
                    text: 'Please select a purpose clause.'
                });
                return;
            }

            if (submitBtn) {
                const originalText = submitBtn.innerText;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="animate-spin mr-2"></i> Processing...';
            }
        });
    }

    // Dynamic Dropdowns for Land Use and Purpose
    const landUseSelect = document.getElementById('land_use_id');
    const purposeSelect = document.getElementById('purpose_id');
    const landUseText = document.getElementById('land_use_text');
    const purposeText = document.getElementById('purpose_of_clause_text');

    if (landUseSelect) {
        landUseSelect.addEventListener('change', function () {
            const landUseId = this.value;
            const landUseName = this.options[this.selectedIndex].text;
            
            // Set text value for hidden input
            if (landUseText) {
                landUseText.value = landUseId ? landUseName : '';
            }

            // Auto-detect term based on Land Use
            const termInput = document.querySelector('input[name="term"]');
            if (termInput && landUseName) {
                const lu = landUseName.toUpperCase().trim();
                if (lu.includes('RESIDENTIAL') || lu.includes('AGRICULTURAL') || lu.includes('AGRICULTURE')) {
                    termInput.value = '99';
                } else if (lu.includes('COMMERCIAL') || lu.includes('INDUSTRIAL')) {
                    termInput.value = '40';
                }
            }

            // Reset Purpose select
            if (purposeSelect) {
                purposeSelect.innerHTML = '<option value="">Loading...</option>';
                purposeSelect.disabled = true;
            }

            if (landUseId) {
                fetch(`/api/reference/purposes?landuseid=${landUseId}`)
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            let options = '<option value="">Select Purpose</option>';
                            result.data.forEach(purpose => {
                                options += `<option value="${purpose.id}">${purpose.name}</option>`;
                            });
                            purposeSelect.innerHTML = options;
                            purposeSelect.disabled = false;
                        } else {
                            purposeSelect.innerHTML = '<option value="">Error loading purposes</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching purposes:', error);
                        purposeSelect.innerHTML = '<option value="">Error loading purposes</option>';
                    });
            } else {
                if (purposeSelect) {
                    purposeSelect.innerHTML = '<option value="">Select Purpose</option>';
                    purposeSelect.disabled = true;
                }
            }
        });
    }

    if (purposeSelect) {
        purposeSelect.addEventListener('change', function() {
            if (purposeText) {
                purposeText.value = this.value ? this.options[this.selectedIndex].text : '';
            }
        });
    }

    // Trigger change event if land use is already selected on page load
    if (landUseSelect && landUseSelect.value) {
        landUseSelect.dispatchEvent(new Event('change'));
    }

    // Initialize Lucide icons if available
    if (window.lucide) {
        window.lucide.createIcons();
    }
});
