<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Recommendation - {{ $recommendation->file_number }}</title>
    <style>
        body { margin: 0; padding: 0; background: white; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body onload="window.print(); setTimeout(() => window.close(), 500);">
    @include('land_recommendations.templates.print_layout', ['recommendation' => $recommendation])

    <script>
        // Log the print after the dialog is closed
        window.onafterprint = function() {
            fetch('{{ route("land-recommendations.log-print", $recommendation->id) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            }).then(response => response.json())
              .then(data => {
                  console.log('Print logged:', data);
                  // Optionally redirect or close if not done in body onload
              })
              .catch(error => console.error('Error logging print:', error));
        };
    </script>
</body>
</html>
