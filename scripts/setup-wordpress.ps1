#requires -Version 5.1
<#
.SYNOPSIS
    Bootstrap (or repair) the local WordPress dev environment for the Corex monorepo on WAMP.

.DESCRIPTION
    Corex is a framework, not a site: this repo holds the framework SOURCE (theme/, plugins/*)
    and a local WordPress install lives in ./wp (gitignored). This script reproduces that
    environment idempotently — safe to re-run, and the single command to run after cloning the
    repo OR after renaming/moving the repo folder (it recreates the wp-content junctions for the
    repo's CURRENT path, so it survives a rename like blackstone-new-site -> corex).

    Steps: ensure the MySQL client is on PATH -> download WP core into ./wp -> create wp-config.php
    -> create the database -> install WordPress -> junction theme/ and plugins/* into
    wp/wp-content -> activate the Corex theme + plugins -> verify.

    Real symlinks (mklink /D) need elevation; this uses directory JUNCTIONS (mklink /J), which do
    not. See DECISIONS.md #18 and the constitution "Environment Gate".

.EXAMPLE
    powershell -File .\scripts\setup-wordpress.ps1
    powershell -File .\scripts\setup-wordpress.ps1 -SiteUrl http://corex.local -AdminEmail you@example.com
#>
[CmdletBinding()]
param(
    [string]$SiteUrl       = 'http://corex.local',
    [string]$Title         = 'Corex',
    [string]$AdminUser     = 'admin',
    [string]$AdminEmail    = 'admin@example.com',
    [string]$AdminPassword = 'changeme',
    [string]$DbName        = 'corex',
    [string]$DbUser        = 'root',
    [string]$DbPass        = '',
    [string]$DbHost        = 'localhost',
    [string]$DbPrefix      = 'cx_',
    [string]$WpDir         = 'wp',
    [string]$MysqlBin      = ''   # auto-detected from WAMP if empty
)

# WP-CLI emits warnings to stderr on idempotent re-runs (e.g. "plugin already active"); under
# 'Stop', PowerShell 5.1 turns native-command stderr into a fatal error. Use 'Continue' and check
# exit codes explicitly on the must-succeed steps.
$ErrorActionPreference = 'Continue'

function Fail([string]$Message) { Write-Host "ERROR: $Message" -ForegroundColor Red; exit 1 }

# Repo root = parent of this scripts/ folder. Path-independent: works after a folder rename.
$Root   = Split-Path -Parent $PSScriptRoot
Set-Location $Root
$WpPath = Join-Path $Root $WpDir

# --- MySQL client on PATH (wp db ... shells out to mysql.exe; WAMP doesn't add it to PATH) ---
if (-not $MysqlBin) {
    $found = Get-ChildItem 'C:\wamp64\bin\mysql\*\bin', 'C:\wamp64\bin\mariadb\*\bin' -ErrorAction SilentlyContinue |
        Where-Object { Test-Path (Join-Path $_.FullName 'mysql.exe') } | Select-Object -First 1
    if ($found) { $MysqlBin = $found.FullName }
}
if ($MysqlBin -and (Test-Path $MysqlBin)) { $env:PATH = "$MysqlBin;$env:PATH" }

Write-Host "== Corex WordPress setup ==  repo: $Root" -ForegroundColor Cyan

# --- 1. WordPress core ---
if (-not (Test-Path (Join-Path $WpPath 'wp-load.php'))) {
    Write-Host "Downloading WordPress core into ./$WpDir ..."
    & wp core download --path="$WpPath" --skip-content --locale=en_US
    if ($LASTEXITCODE -ne 0) { Fail "wp core download failed." }
} else {
    Write-Host "WordPress core already present in ./$WpDir."
}

# --- 2. wp-config.php ---
if (-not (Test-Path (Join-Path $WpPath 'wp-config.php'))) {
    Write-Host "Creating wp-config.php ..."
    & wp config create --path="$WpPath" --dbname="$DbName" --dbuser="$DbUser" `
        --dbpass="$DbPass" --dbhost="$DbHost" --dbprefix="$DbPrefix" --locale=en_US
    if ($LASTEXITCODE -ne 0) { Fail "wp config create failed." }

    # Set the debug constants with WP-CLI rather than piping a here-string into --extra-php.
    # PowerShell 5.1 prepends a UTF-8 BOM when it pipes to a native command, so that here-string
    # arrived as "<U+FEFF>define( 'WP_DEBUG', true );" and landed verbatim in wp-config.php.
    # `wp config create` still exited 0 — the file was written, just corrupt — so the guard above
    # passed and the run died later at `wp core install` with "Call to undefined function define()".
    # Every fresh clone hit this; existing installs did not, because the file was already there.
    foreach ($const in @(
        @{ Name = 'WP_DEBUG';         Value = 'true'  },
        @{ Name = 'WP_DEBUG_LOG';     Value = 'true'  },
        @{ Name = 'WP_DEBUG_DISPLAY'; Value = 'false' }
    )) {
        & wp config set $const.Name $const.Value --raw --type=constant --path="$WpPath" | Out-Null
        if ($LASTEXITCODE -ne 0) { Fail ("Could not set {0} in wp-config.php." -f $const.Name) }
    }
} else {
    Write-Host "wp-config.php already present."
}

# --- 3. Database (create if absent; "already exists" is fine) ---
& wp db create --path="$WpPath" 2>$null
if ($LASTEXITCODE -eq 0) { Write-Host "Database '$DbName' created." }
else { Write-Host "Database '$DbName' already exists (ok)." }

# --- 4. Install (or just align URLs if already installed) ---
& wp core is-installed --path="$WpPath" 2>$null
if ($LASTEXITCODE -ne 0) {
    Write-Host "Installing WordPress ..."
    & wp core install --path="$WpPath" --url="$SiteUrl" --title="$Title" `
        --admin_user="$AdminUser" --admin_email="$AdminEmail" --admin_password="$AdminPassword" --skip-email
    if ($LASTEXITCODE -ne 0) { Fail "wp core install failed." }
} else {
    Write-Host "WordPress already installed; ensuring siteurl/home = $SiteUrl ."
    & wp option update siteurl "$SiteUrl" --path="$WpPath" | Out-Null
    & wp option update home    "$SiteUrl" --path="$WpPath" | Out-Null
}

# --- 5. Map the monorepo into wp-content via junctions (recreated for the current path) ---
function Set-Junction {
    param([string]$Link, [string]$Target)
    # rmdir on a junction removes only the link, never the target. cmd swallows its own stderr.
    cmd /c "if exist `"$Link`" rmdir `"$Link`" 2>nul"
    cmd /c "mklink /J `"$Link`" `"$Target`"" | Out-Null
    Write-Host ("  junction  {0}  ->  {1}" -f ([System.IO.Path]::GetFileName($Link)), $Target)
}
$themesDir  = Join-Path $WpPath 'wp-content\themes'
$pluginsDir = Join-Path $WpPath 'wp-content\plugins'
New-Item -ItemType Directory -Force -Path $themesDir, $pluginsDir | Out-Null

Write-Host "Wiring monorepo -> wp-content:"
Set-Junction (Join-Path $themesDir 'corex') (Join-Path $Root 'theme')
Get-ChildItem (Join-Path $Root 'plugins') -Directory | ForEach-Object {
    Set-Junction (Join-Path $pluginsDir $_.Name) $_.FullName
}
# Add-ons are WP-plugin-shaped Composer packages; junction any that contain a PHP file.
$addonsRoot = Join-Path $Root 'addons'
if (Test-Path $addonsRoot) {
    Get-ChildItem $addonsRoot -Directory -ErrorAction SilentlyContinue |
        Where-Object { Get-ChildItem $_.FullName -Filter '*.php' -ErrorAction SilentlyContinue } |
        ForEach-Object { Set-Junction (Join-Path $pluginsDir $_.Name) $_.FullName }
}

# --- 6. Activate theme + plugins ---
# corex-core FIRST. corex-blocks and corex-config declare "Requires Plugins: corex-core", and WP-CLI
# activates in the order it is given — an alphabetical list puts both ahead of what they depend on
# and WP-CLI reports "Only activated 2 of 4 plugins". The previous comment here claimed WordPress
# resolved that order; it does not. This went unnoticed because a re-run activates whatever failed
# the first time and the exit code was never checked, so a clean run looked identical to a repaired
# one. CI on a fresh install is where it finally showed (PR #120).
& wp theme activate corex --path="$WpPath" | Out-Null
if ($LASTEXITCODE -ne 0) { Fail "Could not activate the Corex theme." }

& wp plugin activate corex-core --path="$WpPath" | Out-Null
if ($LASTEXITCODE -ne 0) { Fail "Could not activate corex-core, which every other plugin requires." }

# Then everything else that was junctioned above, add-ons included. The integration suite resolves
# add-on services from the container, so a site with only plugins/* active is not the environment
# those tests assume.
& wp plugin activate --all --path="$WpPath" | Out-Null
if ($LASTEXITCODE -ne 0) { Fail "Could not activate every Corex plugin - see 'wp plugin list --path=$WpPath'." }

# --- 7. Verify (the constitution's Environment Gate) ---
Write-Host "`n== Verification ==" -ForegroundColor Cyan
& wp theme list --path="$WpPath"
& wp plugin list --path="$WpPath"
Write-Host "`nSite : $SiteUrl"
Write-Host "Admin: $SiteUrl/wp-admin/  ($AdminUser)"
Write-Host "Done." -ForegroundColor Green
