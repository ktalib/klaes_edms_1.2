<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Test Shared Areas Processing</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="checkbox"] { margin-right: 5px; }
        textarea { width: 100%; height: 80px; padding: 5px; }
        button { padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px; }
        #result { margin-top: 20px; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>Test Shared Areas Processing</h1>
    
    <form id="testForm">
        @csrf
        <div class="form-group">
            <label>Select shared areas:</label>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                <label><input type="checkbox" name="shared_areas[]" value="hallways"> Hallways</label>
                <label><input type="checkbox" name="shared_areas[]" value="gardens"> Gardens</label>
                <label><input type="checkbox" name="shared_areas[]" value="parking_lots"> Parking Lots</label>
                <label><input type="checkbox" name="shared_areas[]" value="swimming_pool"> Swimming Pool</label>
                <label><input type="checkbox" name="shared_areas[]" value="gym"> Gym</label>
                <label><input type="checkbox" name="shared_areas[]" value="rooftop"> Rooftop</label>
                <label><input type="checkbox" name="shared_areas[]" value="lobby"> Lobby</label>
                <label><input type="checkbox" name="shared_areas[]" value="elevator"> Elevator</label>
                <label><input type="checkbox" name="shared_areas[]" value="storage"> Storage</label>
                <label><input type="checkbox" name="shared_areas[]" value="other" id="other_checkbox" onchange="toggleOtherText()"> Other</label>
            </div>
        </div>
        
        <div class="form-group" id="other_container" style="display: none;">
            <label for="other_areas_detail">Please specify other shared areas (separated by commas):</label>
            <textarea id="other_areas_detail" name="other_areas_detail" placeholder="e.g., ab,cd,ef,gh"></textarea>
        </div>
        
        <button type="button" onclick="processSharedAreas()">Test Processing (Client-side)</button>
        <button type="button" onclick="testServerProcessing()">Test Server Processing</button>
    </form>
    
    <div id="result"></div>
    
    <script>
        function toggleOtherText() {
            const otherCheckbox = document.getElementById('other_checkbox');
            const otherContainer = document.getElementById('other_container');
            otherContainer.style.display = otherCheckbox.checked ? 'block' : 'none';
        }
        
        function processSharedAreas() {
            const formData = new FormData(document.getElementById('testForm'));
            const sharedAreasArray = formData.getAll('shared_areas[]');
            const otherAreasDetail = formData.get('other_areas_detail');
            
            console.log('Form data:', {
                shared_areas: sharedAreasArray,
                other_areas_detail: otherAreasDetail
            });
            
            // Simulate the controller logic
            let processedArray = [...sharedAreasArray];
            
            if (sharedAreasArray.includes('other') && otherAreasDetail && otherAreasDetail.trim()) {
                // Remove "other" from the array
                processedArray = processedArray.filter(area => area !== 'other');
                
                // Parse the other_areas_detail and add each area to the array
                const customAreas = otherAreasDetail.split(',').map(area => area.trim()).filter(area => area);
                
                // Add custom areas to the shared areas array
                processedArray = [...processedArray, ...customAreas];
            }
            
            const finalJson = JSON.stringify(processedArray);
            
            document.getElementById('result').innerHTML = `Client-side Processing Results:
Raw shared_areas[]: ${JSON.stringify(sharedAreasArray)}
Raw other_areas_detail: "${otherAreasDetail}"
Has "other": ${sharedAreasArray.includes('other')}
Other detail filled: ${!!(otherAreasDetail && otherAreasDetail.trim())}
Processed array: ${JSON.stringify(processedArray)}
Final JSON: ${finalJson}`;
        }
        
        async function testServerProcessing() {
            const formData = new FormData(document.getElementById('testForm'));
            
            try {
                const response = await fetch('/test-shared-areas', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('result').innerHTML = `Server-side Processing Results:
Debug Info: ${JSON.stringify(data.debug, null, 2)}
Final JSON: ${data.final_json}`;
                } else {
                    document.getElementById('result').innerHTML = `Server Error:
${data.message}`;
                }
            } catch (error) {
                document.getElementById('result').innerHTML = `Error:
${error.message}`;
            }
        }
    </script>
</body>
</html>