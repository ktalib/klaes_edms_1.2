# Alternative PowerShell script to execute the SQL sync
# Use this if sqlcmd has SSL certificate issues

$sqlFile = "C:\wamp64\www\klaes\database\migrations\manual\sync_pic_with_pra_columns.sql"
$serverName = "localhost"
$databaseName = "klaes"

Write-Host "================================================" -ForegroundColor Cyan
Write-Host "PIC Table Schema Sync - PowerShell Executor" -ForegroundColor Cyan
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""

try {
    # Read the SQL file
    Write-Host "Reading SQL file..." -ForegroundColor Yellow
    $sqlContent = Get-Content -Path $sqlFile -Raw
    
    # Create connection string (with TrustServerCertificate to bypass SSL issues)
    $connectionString = "Server=$serverName;Database=$databaseName;Integrated Security=True;TrustServerCertificate=True;"
    
    Write-Host "Connecting to database: $databaseName on $serverName" -ForegroundColor Yellow
    
    # Create SQL connection
    $connection = New-Object System.Data.SqlClient.SqlConnection
    $connection.ConnectionString = $connectionString
    $connection.Open()
    
    Write-Host "Connected successfully!" -ForegroundColor Green
    Write-Host "Executing schema sync script..." -ForegroundColor Yellow
    Write-Host ""
    
    # Split by GO statements and execute each batch
    $batches = $sqlContent -split '\r?\nGO\r?\n'
    
    foreach ($batch in $batches) {
        $batch = $batch.Trim()
        if ($batch -ne "") {
            $command = $connection.CreateCommand()
            $command.CommandText = $batch
            $command.CommandTimeout = 300  # 5 minutes timeout
            
            try {
                $result = $command.ExecuteNonQuery()
                
                # Check if batch contains PRINT statement
                if ($batch -match "PRINT\s+'([^']+)'") {
                    $message = $Matches[1]
                    if ($message -match "^Adding:") {
                        Write-Host "$message" -ForegroundColor Green
                    } elseif ($message -match "^Exists:") {
                        Write-Host "$message" -ForegroundColor Gray
                    } else {
                        Write-Host "$message" -ForegroundColor Cyan
                    }
                }
            } catch {
                Write-Host "Error executing batch: $_" -ForegroundColor Red
            }
        }
    }
    
    Write-Host ""
    Write-Host "================================================" -ForegroundColor Cyan
    Write-Host "Schema sync completed!" -ForegroundColor Green
    Write-Host "================================================" -ForegroundColor Cyan
    
} catch {
    Write-Host "Error: $_" -ForegroundColor Red
} finally {
    if ($connection -and $connection.State -eq 'Open') {
        $connection.Close()
        Write-Host "Database connection closed." -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "Press any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
