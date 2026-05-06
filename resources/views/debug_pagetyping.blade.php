<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Page Typing Debug Test</title>
</head>
<body>
    <h1>Page Typing AJAX Debug Test</h1>
    <div id="result"></div>
    <button onclick="testPageTypingData()">Test Page Typing Data</button>

    <script>
        async function testPageTypingData() {
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = 'Testing...';
            
            try {
                console.log('Testing URL:', '{{ route("pagetyping.get-data") }}');
                console.log('CSRF Token:', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                
                const response = await fetch('{{ route("pagetyping.get-data") }}', {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    credentials: 'same-origin'
                });
                
                console.log('Response status:', response.status);
                console.log('Response ok:', response.ok);
                
                if (!response.ok) {
                    const errorText = await response.text();
                    resultDiv.innerHTML = `<div style="color: red;">
                        <h3>HTTP Error: ${response.status} ${response.statusText}</h3>
                        <h4>Response Body:</h4>
                        <pre>${errorText.substring(0, 2000)}</pre>
                    </div>`;
                    return;
                }
                
                const responseText = await response.text();
                console.log('Raw response:', responseText);
                
                try {
                    const data = JSON.parse(responseText);
                    resultDiv.innerHTML = `<div style="color: green;">
                        <h3>✅ Success!</h3>
                        <h4>Data received:</h4>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    </div>`;
                } catch (parseError) {
                    resultDiv.innerHTML = `<div style="color: red;">
                        <h3>JSON Parse Error</h3>
                        <p>Error: ${parseError.message}</p>
                        <h4>Response (first 1000 chars):</h4>
                        <pre>${responseText.substring(0, 1000)}</pre>
                    </div>`;
                }
                
            } catch (error) {
                resultDiv.innerHTML = `<div style="color: red;">
                    <h3>Request Error</h3>
                    <p>${error.message}</p>
                </div>`;
                console.error('Error:', error);
            }
        }
    </script>
</body>
</html>