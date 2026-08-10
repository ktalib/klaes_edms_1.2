<#
    Back up the production copies of the 60 files in this deployment drop,
    BEFORE overwriting them.

    Reads MANIFEST.txt (sitting next to this script), pulls each of those paths
    from the production root, and writes them into a timestamped folder in the
    same mirror structure. Restoring is then just a copy back the other way.

    RUN THIS ON THE PRODUCTION (XAMPP) MACHINE, from inside the copied
    _klaes_deploy folder. It defaults to C:\xampp\htdocs\klas; pass -ProdRoot only
    if the project sits somewhere else.

        .\backup-production.ps1
        .\backup-production.ps1 -ProdRoot "D:\xampp\htdocs\klas"
        .\backup-production.ps1 -BackupRoot "D:\backups"

    Nothing is written to the production folder — this only reads from it.
#>

[CmdletBinding()]
param(
    # Production project root: the folder that directly contains app\, public\, routes\.
    [string]$ProdRoot,

    # Where the timestamped backup folder is created. Defaults to next to this script.
    [string]$BackupRoot
)

$ErrorActionPreference = 'Stop'

# $PSScriptRoot is only auto-populated in PowerShell 3+; on older hosts it is empty
# inside a plain script, which used to blow up every path built from it below.
$ScriptDir = $PSScriptRoot
if (-not $ScriptDir) { $ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition }
if (-not $ScriptDir) { $ScriptDir = (Get-Location).Path }

if (-not $BackupRoot) { $BackupRoot = $ScriptDir }

Write-Host ("PowerShell {0} | script dir: {1}" -f $PSVersionTable.PSVersion, $ScriptDir) -ForegroundColor DarkGray

if (-not $ProdRoot) {
    # A real project root has all three of these. Anything less is a wrong guess.
    # C:\xampp\htdocs\klas is the real one — note "klas", not "klaes".
    $candidates = @(
        'C:\xampp\htdocs\klas', 'D:\xampp\htdocs\klas', 'E:\xampp\htdocs\klas',
        'C:\xampp\htdocs\klaes', 'D:\xampp\htdocs\klaes'
    )
    $ProdRoot = $candidates | Where-Object {
        (Test-Path (Join-Path $_ 'app')) -and
        (Test-Path (Join-Path $_ 'public')) -and
        (Test-Path (Join-Path $_ 'routes'))
    } | Select-Object -First 1

    if (-not $ProdRoot) {
        throw "Could not find the project under htdocs. Re-run with the path, e.g.`n    .\backup-production.ps1 -ProdRoot ""C:\xampp\htdocs\yourfolder"""
    }
    Write-Host "Found production project at: $ProdRoot" -ForegroundColor Cyan
}

if (-not (Test-Path -LiteralPath $ProdRoot)) {
    throw "Production root not found: $ProdRoot"
}

# Refuse to "back up" the deployment folder itself — that would silently produce
# an empty-looking backup that offers no rollback at all.
if ((Resolve-Path $ProdRoot).Path -eq (Resolve-Path $ScriptDir).Path) {
    throw "-ProdRoot points at this deployment folder, not the live project."
}

$manifest = Join-Path $ScriptDir 'MANIFEST.txt'
if (-not (Test-Path -LiteralPath $manifest)) {
    throw "MANIFEST.txt not found beside this script ($ScriptDir)."
}

$stamp  = Get-Date -Format 'yyyy-MM-dd_HHmmss'
$dest   = Join-Path $BackupRoot "klaes_prod_backup_$stamp"
New-Item -ItemType Directory -Force -Path $dest | Out-Null

# ArrayList and .Trim() rather than List[string]/IsNullOrWhiteSpace: this has to run
# on PowerShell 2.0, where the generic-type syntax and that .NET 4 method are absent.
$backed = New-Object System.Collections.ArrayList
$absent = New-Object System.Collections.ArrayList

# How many misses are legitimate: the release's NEW files aren't on production yet.
$expectedNew = @(Get-Content -LiteralPath $manifest | Where-Object { $_ -like 'NEW*' }).Count

foreach ($line in (Get-Content -LiteralPath $manifest)) {
    if (-not $line -or -not $line.Trim()) { continue }

    # "MOD  app/Http/Controllers/Foo.php" -> tag + relative path
    $tag = $line.Substring(0, 3).Trim()
    $rel = $line.Substring(3).Trim() -replace '/', '\'

    $s = Join-Path $ProdRoot $rel

    if (Test-Path -LiteralPath $s -PathType Leaf) {
        $d = Join-Path $dest $rel
        New-Item -ItemType Directory -Force -Path (Split-Path $d -Parent) | Out-Null
        Copy-Item -LiteralPath $s -Destination $d -Force
        [void]$backed.Add($rel)
    }
    else {
        # Expected for every NEW file — it does not exist on production yet.
        [void]$absent.Add("$tag  $rel")
    }
}

# Record what was NOT backed up, so a restore knows which files to DELETE rather
# than copy back. Without this, rolling back would leave the new files orphaned.
$absentFile = Join-Path $dest '_NOT_ON_PRODUCTION.txt'
@(
    "These paths did not exist on production at backup time ($stamp)."
    "To roll this deployment back fully: copy the backup tree over production,"
    "then DELETE these files from production."
    ""
) + $absent | Out-File -FilePath $absentFile -Encoding utf8

@(
    "Source (production): $ProdRoot"
    "Taken:               $stamp"
    "Backed up:           $($backed.Count) file(s)"
    "Not on production:   $($absent.Count) file(s) - see _NOT_ON_PRODUCTION.txt"
) | Out-File -FilePath (Join-Path $dest 'BACKUP_INFO.txt') -Encoding utf8

Write-Host ""
Write-Host "Backup written to: $dest" -ForegroundColor Green
Write-Host "  backed up        : $($backed.Count)"
Write-Host "  not on production: $($absent.Count) (expected $expectedNew - the new files)"

# More misses than the release has new files means we are reading the wrong folder,
# and a backup of nothing is worse than no backup: it looks like a safety net.
if ($absent.Count -gt $expectedNew) {
    Write-Host ""
    Write-Host "WARNING: more misses than the $expectedNew new files. Is -ProdRoot pointing" -ForegroundColor Yellow
    Write-Host "at the project ROOT (the folder containing app\, public\, routes\)?" -ForegroundColor Yellow
}
