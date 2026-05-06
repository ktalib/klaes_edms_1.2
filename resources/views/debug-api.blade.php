@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-4">API Debug Test</h1>
    
    <div class="bg-white p-6 rounded-lg shadow">
        <h2 class="text-lg font-semibold mb-4">Test API Authentication</h2>
        
        <div class="space-y-4">
            <button onclick="testStatistics()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                Test Statistics API
            </button>
            
            <button onclick="testIndexedFiles()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                Test Indexed Files API
            </button>
            
            <button onclick="testPendingFiles()" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">
                Test Pending Files API
            </button>
        </div>
        
        <div id="results" class="mt-6 p-4 bg-gray-100 rounded">
            <h3 class="font-semibold">Results will appear here...</h3>
        </div>
    </div>
</div>

<script>
    function logResult(title, response, data) {
        const resultsDiv = document.getElementById('results');
        resultsDiv.innerHTML += `
            <div class="mb-4 p-3 border-l-4 border-blue-500">
                <h4 class="font-bold text-blue-700">${title}</h4>
                <p><strong>Status:</strong> ${response.status}</p>
                <p><strong>Response:</strong></p>
                <pre class="bg-white p-2 rounded text-sm overflow-auto">${JSON.stringify(data, null, 2)}</pre>
            </div>
        `;
    }

    async function testStatistics() {
        try {
            const response = await fetch('/fileindexing/api/statistics', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                credentials: 'include'
            });
            
            const data = await response.json();
            logResult('Statistics API', response, data);
        } catch (error) {
            logResult('Statistics API', {status: 'ERROR'}, {error: error.message});
        }
    }

    async function testIndexedFiles() {
        try {
            const response = await fetch('/fileindexing/api/indexed-files?per_page=5', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                credentials: 'include'
            });
            
            const data = await response.json();
            logResult('Indexed Files API', response, data);
        } catch (error) {
            logResult('Indexed Files API', {status: 'ERROR'}, {error: error.message});
        }
    }

    async function testPendingFiles() {
        try {
            const response = await fetch('/fileindexing/api/pending-files?per_page=5', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                credentials: 'include'
            });
            
            const data = await response.json();
            logResult('Pending Files API', response, data);
        } catch (error) {
            logResult('Pending Files API', {status: 'ERROR'}, {error: error.message});
        }
    }
</script>
@endsection