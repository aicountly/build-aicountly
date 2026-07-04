#Requires -Version 5.1
<#
.SYNOPSIS
  Sync GitHub Actions secrets onto build-aicountly.

.DESCRIPTION
  GitHub never returns secret values via API, so "copy from reach" means:
    1. List secret names on aicountly/reach-aicountly (optional check)
    2. Apply key=value pairs from a local env file to aicountly/build-aicountly

  Copy values from reach in the browser:
    reach-aicountly → Settings → Secrets and variables → Actions
    Paste each value into .github/secrets.local.env (copy from secrets.env.example)

.PARAMETER EnvFile
  Path to KEY=VALUE file (default: .github/secrets.env.example placeholders)

.PARAMETER TargetRepo
  Destination repo (default: aicountly/build-aicountly)

.PARAMETER SourceRepo
  Source repo to list names from (default: aicountly/reach-aicountly)
#>
param(
    [string]$EnvFile = ".github/secrets.env.example",
    [string]$TargetRepo = "aicountly/build-aicountly",
    [string]$SourceRepo = "aicountly/reach-aicountly"
)

$ErrorActionPreference = "Stop"
$root = Resolve-Path (Join-Path $PSScriptRoot "..\..")
Set-Location $root

if (-not (Get-Command gh -ErrorAction SilentlyContinue)) {
    Write-Error "GitHub CLI (gh) is required. Install from https://cli.github.com/ then run: gh auth login"
}

$auth = gh auth status 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Error "Not logged in to GitHub. Run: gh auth login"
}

Write-Host "Source repo (reference): $SourceRepo" -ForegroundColor Cyan
Write-Host "Target repo:             $TargetRepo" -ForegroundColor Cyan
Write-Host ""

Write-Host "Secrets currently on reach-aicountly:" -ForegroundColor Yellow
gh secret list -R $SourceRepo 2>$null
Write-Host ""

Write-Host "Secrets currently on build-aicountly:" -ForegroundColor Yellow
gh secret list -R $TargetRepo 2>$null
Write-Host ""

$envPath = Join-Path $root $EnvFile
if (-not (Test-Path $envPath)) {
    Write-Error "Env file not found: $envPath`nCopy .github/secrets.env.example to .github/secrets.local.env and fill in values from reach."
}

Write-Host "Applying secrets from: $envPath" -ForegroundColor Green

$lines = Get-Content $envPath | Where-Object {
    $_ -match '^\s*[^#]' -and $_ -match '='
}

$count = 0
foreach ($line in $lines) {
    if ($line -match '^\s*#') { continue }
    $idx = $line.IndexOf('=')
    if ($idx -lt 1) { continue }

    $name  = $line.Substring(0, $idx).Trim()
    $value = $line.Substring($idx + 1)

    if ($name -eq '') { continue }

    Write-Host "  Setting $name ..."
    # gh secret set on Windows: pass value via --body (supports multiline SSH keys)
    gh secret set $name -R $TargetRepo --body $value | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Write-Error "Failed to set secret: $name"
    }
    $count++
}

Write-Host ""
Write-Host "Done — set $count secret(s) on $TargetRepo." -ForegroundColor Green
Write-Host "Update placeholder values in GitHub → $TargetRepo → Settings → Secrets when ready."
Write-Host ""
Write-Host "Verify:" -ForegroundColor Yellow
gh secret list -R $TargetRepo
