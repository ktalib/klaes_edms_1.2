<#
    Build a production deployment folder from whatever this repo says has changed.

    Production (C:\xampp\htdocs\klas) has no git, so releases are done by copying
    files over. This assembles exactly the changed files into a mirror of the
    project structure, plus the backup/migration helpers, so the release is
    "copy six folders across" instead of hunting files one by one.

    USAGE

        .\scripts\deploy\make-deploy.ps1
            Everything not yet committed (modified + untracked). Use before committing.

        .\scripts\deploy\make-deploy.ps1 -Since HEAD~1
        .\scripts\deploy\make-deploy.ps1 -Since 73f4281
            Everything that changed between that commit and HEAD. Use AFTER
            committing — the working tree is clean then, so the default finds nothing.
            Pass the last commit that is already live on production.

        .\scripts\deploy\make-deploy.ps1 -Since 73f4281 -IncludeUncommitted
            Both: committed since that ref, plus anything still uncommitted.

        .\scripts\deploy\make-deploy.ps1 -Out D:\drops\release-12

        .\scripts\deploy\make-deploy.ps1 -Paths 'routes\web.php','config\app.php'
        .\scripts\deploy\make-deploy.ps1 -Paths (Get-Content .\release.txt)
            Ship exactly these files, ignoring what git thinks changed. Use when the
            release is one feature out of a working tree (or a history) that also
            contains unrelated work. Repo-relative paths.

            NEW vs MOD is guessed from the repo: untracked means NEW. Add -Since to
            decide it properly - a file absent from that ref is NEW to production.

    Read-only against the repo. Only the -Out folder is written to.
#>

[CmdletBinding()]
param(
    # Base ref. Files changed between it and HEAD are included.
    [string]$Since,

    # Add working-tree changes on top of -Since.
    [switch]$IncludeUncommitted,

    # Explicit repo-relative paths to ship. Overrides git change detection entirely.
    [string[]]$Paths,

    [string]$Out = 'C:\wamp64\www\_klaes_deploy',

    # Skip the "this will erase the existing folder" prompt.
    [switch]$Force
)

$ErrorActionPreference = 'Stop'

$RepoRoot = (git rev-parse --show-toplevel 2>$null)
if (-not $RepoRoot) { throw "Not inside a git repository." }
$RepoRoot = $RepoRoot -replace '/', '\'

$AssetDir = Join-Path $RepoRoot 'scripts\deploy\assets'

# --- collect changed paths -------------------------------------------------
# Two sources, both normalised to @{ Path; State } where State is NEW/MOD/DEL.
$changes = @{}   # path -> state, so a file touched by both sources lands once

function Add-Change([string]$path, [string]$state) {
    if (-not $path) { return }
    $p = $path.Trim().Trim('"')
    # A file created then later modified is still NEW to production.
    if ($changes.ContainsKey($p) -and $changes[$p] -eq 'NEW' -and $state -eq 'MOD') { return }
    $changes[$p] = $state
}

if ($Paths) {
    # Explicit list: the caller has already decided what ships. git is consulted only
    # to label each file NEW or MOD, never to add or drop one.
    foreach ($raw in $Paths) {
        if (-not $raw) { continue }
        $rel = ($raw.Trim().Trim('"')) -replace '/', '\'
        if (-not $rel) { continue }

        if (-not (Test-Path -LiteralPath (Join-Path $RepoRoot $rel) -PathType Leaf)) {
            throw "Not a file in the repo: $rel"
        }

        $state = 'MOD'
        $slashed = $rel -replace '\\', '/'

        # ls-tree / ls-files are used rather than cat-file -e and --error-unmatch
        # because those write to stderr for a path they cannot find, and under
        # $ErrorActionPreference = 'Stop' PowerShell turns a native command's stderr
        # into a terminating NativeCommandError. These two stay silent and simply
        # return nothing, so "absent" is read from the output, not from an exit code.
        if ($Since) {
            # Absent from the base ref means production has never seen it.
            $found = git ls-tree -r --name-only $Since -- $slashed
        } else {
            $found = git ls-files -- $rel
        }

        if (-not $found) { $state = 'NEW' }

        Add-Change $rel $state
    }
}
elseif ($Since) {
    git rev-parse --verify --quiet "$Since" > $null
    if ($LASTEXITCODE -ne 0) { throw "Not a valid commit/ref: $Since" }

    foreach ($line in (git diff --name-status $Since HEAD)) {
        if (-not $line) { continue }
        $parts = $line -split "`t"
        $code  = $parts[0]
        switch -Regex ($code) {
            '^A'      { Add-Change $parts[1] 'NEW' }
            '^[MT]'   { Add-Change $parts[1] 'MOD' }
            '^D'      { Add-Change $parts[1] 'DEL' }
            # Rename/copy: destination ships, source is removed on production.
            '^R'      { Add-Change $parts[2] 'NEW'; Add-Change $parts[1] 'DEL' }
            '^C'      { Add-Change $parts[2] 'NEW' }
        }
    }
}

if (-not $Paths -and (-not $Since -or $IncludeUncommitted)) {
    # -uall: without it git collapses a wholly-untracked directory into a single
    # "dir/" entry, and every file inside it would silently miss the release.
    foreach ($line in (git status --porcelain -uall)) {
        if (-not $line) { continue }
        $xy   = $line.Substring(0, 2)
        $rest = $line.Substring(3)

        if ($xy -match 'R') {
            $pair = $rest -split ' -> '
            Add-Change $pair[1] 'NEW'
            Add-Change $pair[0] 'DEL'
            continue
        }

        if ($xy -match 'D') { Add-Change $rest 'DEL';  continue }
        if ($xy -eq '??')   { Add-Change $rest 'NEW';  continue }
        if ($xy -match 'A') { Add-Change $rest 'NEW';  continue }
        Add-Change $rest 'MOD'
    }
}

if ($changes.Count -eq 0) {
    Write-Host "Nothing to deploy." -ForegroundColor Yellow
    if (-not $Since) {
        Write-Host "The working tree is clean. If you have already committed, re-run with" -ForegroundColor Yellow
        Write-Host "  -Since <last commit live on production>" -ForegroundColor Yellow
    }
    return
}

# --- prepare the output folder ---------------------------------------------
if (Test-Path -LiteralPath $Out) {
    if (-not $Force) {
        Write-Host ""
        Write-Host "About to ERASE and rebuild: $Out" -ForegroundColor Yellow
        $answer = Read-Host "Type YES to continue"
        if ($answer -cne 'YES') { Write-Host "Aborted."; return }
    }
    Remove-Item -LiteralPath $Out -Recurse -Force
}
New-Item -ItemType Directory -Force -Path $Out | Out-Null

$manifest   = New-Object System.Collections.ArrayList
$deleted    = New-Object System.Collections.ArrayList
$missing    = New-Object System.Collections.ArrayList
$migrations = New-Object System.Collections.ArrayList

# This tooling is dev-side; production never runs it from inside the project.
$excluded = @('scripts\deploy\')

foreach ($p in ($changes.Keys | Sort-Object)) {
    $state = $changes[$p]
    $rel   = $p -replace '/', '\'

    $skip = $false
    foreach ($e in $excluded) { if ($rel.StartsWith($e, 'OrdinalIgnoreCase')) { $skip = $true } }
    if ($skip) { continue }

    if ($state -eq 'DEL') {
        [void]$deleted.Add($rel)
        continue
    }

    $src = Join-Path $RepoRoot $rel
    if (-not (Test-Path -LiteralPath $src -PathType Leaf)) {
        [void]$missing.Add($rel)
        continue
    }

    $dst = Join-Path $Out $rel
    New-Item -ItemType Directory -Force -Path (Split-Path $dst -Parent) | Out-Null
    Copy-Item -LiteralPath $src -Destination $dst -Force
    [void]$manifest.Add(("{0}  {1}" -f $state, $rel))

    if ($state -eq 'NEW' -and $rel -like 'database\migrations\*.php') {
        [void]$migrations.Add((Split-Path $rel -Leaf))
    }
}

$manifest | Sort-Object | Out-File -FilePath (Join-Path $Out 'MANIFEST.txt') -Encoding utf8

# --- helper scripts ---------------------------------------------------------
if (Test-Path -LiteralPath $AssetDir) {
    Copy-Item (Join-Path $AssetDir '*') -Destination $Out -Recurse -Force
}

# Deletions can't be done by copying — production needs an explicit removal list.
if ($deleted.Count -gt 0) {
    @(
        "Files DELETED in this release. Copying the folders across will NOT remove"
        "them - delete these from C:\xampp\htdocs\klas by hand after copying."
        ""
    ) + ($deleted | Sort-Object) | Out-File -FilePath (Join-Path $Out 'DELETE-ON-PRODUCTION.txt') -Encoding utf8
}

# The migration runner is generated per release, since each ships its own set.
if ($migrations.Count -gt 0) {
    $lines = New-Object System.Collections.ArrayList
    [void]$lines.Add('@echo off')
    [void]$lines.Add('setlocal enabledelayedexpansion')
    [void]$lines.Add('REM Generated by scripts\deploy\make-deploy.ps1 - runs ONLY this release''s migrations.')
    [void]$lines.Add('REM A bare "php artisan migrate" would also run older pending migrations.')
    [void]$lines.Add('')
    [void]$lines.Add('set "PROJECT=C:\xampp\htdocs\klas"')
    [void]$lines.Add('set "PHP=C:\xampp\php\php.exe"')
    [void]$lines.Add('')
    [void]$lines.Add('if not exist "%PROJECT%\artisan" echo   ERROR: no artisan at %PROJECT% & goto :end')
    [void]$lines.Add('if not exist "%PHP%" echo   ERROR: no php.exe at %PHP% & goto :end')
    [void]$lines.Add('cd /d "%PROJECT%"')
    [void]$lines.Add('set FAILED=0')
    [void]$lines.Add('echo.')
    foreach ($m in ($migrations | Sort-Object)) {
        [void]$lines.Add(("call :run {0}" -f $m))
    }
    [void]$lines.Add('echo.')
    [void]$lines.Add('if "!FAILED!"=="0" (echo   All migrations completed.) else (echo   *** !FAILED! FAILED - read the errors above. ***)')
    [void]$lines.Add('goto :end')
    [void]$lines.Add('')
    [void]$lines.Add(':run')
    [void]$lines.Add('echo   -^> %~1')
    [void]$lines.Add('REM Running before the files are copied gives a bare Laravel stack trace.')
    [void]$lines.Add('REM Say what is actually wrong instead.')
    [void]$lines.Add('if not exist "%PROJECT%\database\migrations\%~1" (')
    [void]$lines.Add('  echo      NOT ON PRODUCTION YET - copy the release folders into')
    [void]$lines.Add('  echo      %PROJECT% first, then re-run this script.')
    [void]$lines.Add('  set /a FAILED+=1')
    [void]$lines.Add('  echo.')
    [void]$lines.Add('  exit /b 0')
    [void]$lines.Add(')')
    [void]$lines.Add('"%PHP%" artisan migrate --force --path=database/migrations/%~1')
    [void]$lines.Add('if errorlevel 1 (set /a FAILED+=1 & echo      FAILED)')
    [void]$lines.Add('echo.')
    [void]$lines.Add('exit /b 0')
    [void]$lines.Add('')
    [void]$lines.Add(':end')
    [void]$lines.Add('echo.')
    [void]$lines.Add('pause')

    $lines | Out-File -FilePath (Join-Path $Out '2-RUN-MIGRATIONS.bat') -Encoding ascii
}

# --- summary ---------------------------------------------------------------
$new = ($manifest | Where-Object { $_ -like 'NEW*' }).Count
$mod = ($manifest | Where-Object { $_ -like 'MOD*' }).Count

$source = if ($Paths)                            { "explicit list ($($Paths.Count) path(s))" }
          elseif ($Since -and $IncludeUncommitted) { "$Since..HEAD + uncommitted" }
          elseif ($Since)                      { "$Since..HEAD" }
          else                                 { "uncommitted working tree" }

@(
    "Release built:  $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
    "Source:         $source"
    "Repo:           $RepoRoot"
    "Modified:       $mod"
    "New:            $new"
    "Deleted:        $($deleted.Count)"
    "Migrations:     $($migrations.Count)"
) | Out-File -FilePath (Join-Path $Out 'RELEASE-INFO.txt') -Encoding utf8

Write-Host ""
Write-Host "Deployment folder: $Out" -ForegroundColor Green
Write-Host "  source    : $source"
Write-Host "  modified  : $mod"
Write-Host "  new       : $new"
Write-Host "  deleted   : $($deleted.Count)$(if ($deleted.Count) { '  -> see DELETE-ON-PRODUCTION.txt' })"
Write-Host "  migrations: $($migrations.Count)$(if ($migrations.Count) { '  -> 2-RUN-MIGRATIONS.bat generated' })"

if ($missing.Count -gt 0) {
    Write-Host ""
    Write-Host "WARNING: $($missing.Count) path(s) listed as changed but not found on disk:" -ForegroundColor Yellow
    $missing | ForEach-Object { Write-Host "  $_" -ForegroundColor Yellow }
}

Write-Host ""
Write-Host "Next: copy the folder to production, run 1-BACKUP-PRODUCTION.bat there," -ForegroundColor Cyan
Write-Host "then copy the project folders over C:\xampp\htdocs\klas." -ForegroundColor Cyan
