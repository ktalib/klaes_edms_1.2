<!DOCTYPE html>
<html>
<head>
    <title>HTMX Debug Test</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="p-8">
    <h1 class="text-2xl font-bold mb-4">HTMX Debug Test Page</h1>
    
    <div class="space-y-4">
        <div class="text-gray-700">HTMX primaryform test routes were removed in favor of Livewire. This page is kept for reference only.</div>
        
        <div>
            <form hx-post="{{ route('primaryform.process-csv') }}" 
                  hx-target="#result" 
                  hx-encoding="multipart/form-data"
                  class="border-2 border-dashed border-gray-300 rounded p-4">
                @csrf
                <label class="block mb-2 font-medium">Upload CSV File:</label>
                <input type="file" name="csv_file" accept=".csv" required class="mb-2">
                <button type="submit" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                    Upload CSV
                </button>
            </form>
        </div>
    </div>
    
    <div id="result" class="mt-8 p-4 border rounded bg-gray-50 min-h-[100px]">
        <em>Results will appear here...</em>
    </div>
    
    <script>
        // Configure HTMX with CSRF token
        document.addEventListener('DOMContentLoaded', function() {
            document.body.addEventListener('htmx:configRequest', function(event) {
                const token = document.querySelector('meta[name="csrf-token"]');
                if (token) {
                    event.detail.headers['X-CSRF-TOKEN'] = token.getAttribute('content');
                }
            });
        });
        
        // Log HTMX events
        document.addEventListener('htmx:afterRequest', function(event) {
            console.log('HTMX request:', event.detail);
        });
        
        document.addEventListener('htmx:responseError', function(event) {
            console.error('HTMX error:', event.detail);
            document.getElementById('result').innerHTML = 
                '<div class="bg-red-100 border border-red-400 text-red-700 p-2 rounded">' +
                'Error: ' + event.detail.xhr.status + ' - ' + event.detail.xhr.statusText +
                '</div>';
        });
    </script>
</body>
</html>