# Create a small deploy zip (excludes Node junk).
# Run:  powershell -ExecutionPolicy Bypass -File scripts\make-deploy-zip.ps1

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$stamp = Get-Date -Format "yyyyMMdd-HHmm"
$out = Join-Path $root "Webmonitor-deploy-$stamp.zip"
$stage = Join-Path $env:TEMP "webmonitor-deploy-$stamp"

if (Test-Path $stage) { Remove-Item $stage -Recurse -Force }
New-Item -ItemType Directory -Path $stage | Out-Null

$include = @(
  "lib",
  "includes",
  "assets",
  "cli",
  "database",
  "portal",
  "api",
  "storage",
  ".htaccess",
  "index.php",
  "login.php",
  "logout.php",
  "dashboard.php",
  "websites.php",
  "website.php",
  "website-form.php",
  "website-actions.php",
  "status.php",
  "status-site.php",
  "logs.php",
  "settings.php",
  "manual.php",
  "health.php",
  "db-check.php",
  "telegram.php",
  "audit.php",
  "README.md"
)

foreach ($item in $include) {
  $src = Join-Path $root $item
  if (-not (Test-Path $src)) { continue }
  $dest = Join-Path $stage $item
  if ((Get-Item $src).PSIsContainer) {
    Copy-Item $src $dest -Recurse -Force
  } else {
    $destDir = Split-Path $dest -Parent
    if (-not (Test-Path $destDir)) { New-Item -ItemType Directory -Path $destDir | Out-Null }
    Copy-Item $src $dest -Force
  }
}

# Keep api/.env.example only — never ship secrets if present as .env
$envPath = Join-Path $stage "api\.env"
if (Test-Path $envPath) {
  Remove-Item $envPath -Force
  Write-Host "Removed api/.env from zip (upload secrets separately on server)."
}

if (Test-Path $out) { Remove-Item $out -Force }
Compress-Archive -Path (Join-Path $stage "*") -DestinationPath $out -CompressionLevel Optimal

$size = [math]::Round((Get-Item $out).Length / 1MB, 2)
Remove-Item $stage -Recurse -Force

Write-Host ""
Write-Host "Created: $out"
Write-Host "Size: ${size} MB"
Write-Host "Excluded: backend/, frontend/, node_modules/, public SPA, docker, etc."
