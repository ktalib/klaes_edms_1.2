/**
 * "Application for Conversion" printing — shared by the MLS File Commissioning
 * table and the ST File Commissioning table.
 *
 * Both screens ask the same question (how was the property acquired?) and open the
 * same server-rendered sheet (FileNumberController::generateConversionApplication),
 * so the prompt lives here rather than being duplicated per page.
 *
 * The file number is preferred over the numeric id: the backend resolves it through
 * fileNumber.mlsfNo and can fall back to plot_extensions, and it cannot collide with
 * a different row that happens to share the id.
 */
(function (window) {
    'use strict';

    function promptAcquisitionMethod(fileNo) {
        return Swal.fire({
            title: 'Property Acquisition Method',
            html: `
                <div class="text-left space-y-3 p-2">
                    <p class="text-sm text-gray-600 mb-4">Please select how the property for <strong>${fileNo}</strong> was acquired:</p>
                    <div class="space-y-2">
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="a" class="w-4 h-4 text-blue-600" checked>
                            <span class="text-sm font-medium text-gray-700">a. By Purchase</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="b" class="w-4 h-4 text-blue-600">
                            <span class="text-sm font-medium text-gray-700">b. By Inheritance</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="c" class="w-4 h-4 text-blue-600">
                            <span class="text-sm font-medium text-gray-700">c. By Gift</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="d" class="w-4 h-4 text-blue-600">
                            <span class="text-sm font-medium text-gray-700">d. Direct Local Government Allocation</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="e" class="w-4 h-4 text-blue-600" id="method_other_radio">
                            <span class="text-sm font-medium text-gray-700">e. Any other (Specify)</span>
                        </label>
                    </div>
                    <div id="other_specify_container" class="mt-3 hidden animate-fadeIn">
                        <input type="text" id="other_specify_input"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                               placeholder="Please specify other method...">
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Generate Document',
            cancelButtonText: 'Cancel',
            didOpen: () => {
                const radios = document.querySelectorAll('input[name="acquisition_method"]');
                const otherContainer = document.getElementById('other_specify_container');
                const otherInput = document.getElementById('other_specify_input');

                radios.forEach(radio => {
                    radio.addEventListener('change', (e) => {
                        if (e.target.value === 'e') {
                            otherContainer.classList.remove('hidden');
                            otherInput.focus();
                        } else {
                            otherContainer.classList.add('hidden');
                        }
                    });
                });
            },
            preConfirm: () => {
                const selectedMethod = document.querySelector('input[name="acquisition_method"]:checked').value;
                const otherValue = document.getElementById('other_specify_input').value;

                if (selectedMethod === 'e' && !otherValue.trim()) {
                    Swal.showValidationMessage('Please specify the other acquisition method');
                    return false;
                }

                return { method: selectedMethod, other: otherValue };
            }
        });
    }

    function generateConversionApplication(id, fileNo) {
        return promptAcquisitionMethod(fileNo).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            const { method, other } = result.value;
            const convKey = (fileNo && fileNo !== '--' && fileNo !== 'N/A')
                ? encodeURIComponent(fileNo)
                : id;

            let url = `/file-numbers/conversion-application/${convKey}?method=${method}`;
            if (method === 'e' && other) {
                url += `&other=${encodeURIComponent(other)}`;
            }

            window.open(url, '_blank');
        });
    }

    window.promptConversionAcquisitionMethod = promptAcquisitionMethod;
    window.generateConversionApplication = generateConversionApplication;
})(window);
