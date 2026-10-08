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
    wp/wp-content -> copy the browser suite's fixtures into wp/wp-content/mu-plugins -> activate
    the Corex theme + plugins -> install the Corex schema -> verify.

    The fixtures are every tests/e2e/fixtures/corex-e2e-*.php, the files the browser job in
    .github/workflows/ci.yml copies. They go into the single-site install only, and not into one
    that has a client site linked: there the command to copy them is printed at the end.

    In a client repository it also junctions every client plugin and theme under sites/ into the
    single-site install (spec 102). Those are linked and not activated: switching a client's
    plugin or theme on is its owner's decision, and the commands to do it are printed at the end.
    With no sites/ directory the script does exactly what it did before.

    Real symlinks (mklink /D) need elevation; this uses directory JUNCTIONS (mklink /J), which do
    not. See DECISIONS.md #18 and the constitution "Environment Gate".

.EXAMPLE
    powershell -File .\scripts\setup-wordpress.ps1
    powershell -File .\scripts\setup-wordpress.ps1 -SiteUrl http://corex.local -AdminEmail you@example.com

.EXAMPLE
    powershell -File .\scripts\setup-wordpress.ps1 -Multisite

    Builds the WordPress Multisite network the multisite integration suite needs, in ./wp-ms with
    the cxms_ table prefix, alongside (not instead of) the single-site install in ./wp. Same
    database, different prefix, so the two do not collide.

    It mirrors .github/actions/provision-wordpress with multisite: 'true' — same network
    activations, same three fixture sites — because the suite asserts against that exact shape.
    site2 exists before corex-email is activated on it alone; site3 is created afterwards, so
    wp_initialize_site is the only route its CoreX tables can have arrived by.

    No vhost is required: `composer test:multisite` loads ./wp-ms/wp-load.php directly and drives
    the other sites through WP-CLI, so nothing here has to be reachable over HTTP.
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
    [string]$MysqlBin      = '',  # auto-detected from WAMP if empty
    [switch]$Multisite
)

# -Multisite is a second install, not a different one: ./wp-ms with the cxms_ prefix, matching
# .github/actions/provision-wordpress so the local suite and CI assert against the same network.
# Only defaults move — an explicitly passed -WpDir or -DbPrefix still wins.
if ($Multisite) {
    if (-not $PSBoundParameters.ContainsKey('WpDir'))    { $WpDir    = 'wp-ms' }
    if (-not $PSBoundParameters.ContainsKey('DbPrefix')) { $DbPrefix = 'cxms_' }
}

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
# For -Multisite this MUST ask about the network, not the site. `wp core is-installed` answers for
# a single site, and a network's tables satisfy it — so if the database survives while wp-config.php
# does not (delete ./wp-ms, re-run), the check passes, the install step is skipped, and the freshly
# written wp-config.php never receives the MULTISITE constants. The run then fails several steps
# later with "This is not a multisite installation", pointing at the theme rather than the config.
if ($Multisite) { & wp core is-installed --network --path="$WpPath" 2>$null }
else            { & wp core is-installed           --path="$WpPath" 2>$null }
if ($LASTEXITCODE -ne 0) {
    if ($Multisite) {
        Write-Host "Installing WordPress Multisite (subdirectory) ..."
        & wp core multisite-install --path="$WpPath" --url="$SiteUrl" --title="$Title" `
            --admin_user="$AdminUser" --admin_email="$AdminEmail" --admin_password="$AdminPassword" --skip-email
        if ($LASTEXITCODE -ne 0) { Fail "wp core multisite-install failed." }
    } else {
        Write-Host "Installing WordPress ..."
        & wp core install --path="$WpPath" --url="$SiteUrl" --title="$Title" `
            --admin_user="$AdminUser" --admin_email="$AdminEmail" --admin_password="$AdminPassword" --skip-email
        if ($LASTEXITCODE -ne 0) { Fail "wp core install failed." }
    }
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

# Every client plugin and theme under sites/ (spec 102), as what to link and where from. A client
# site lives at sites/<client>/ in one of two layouts, the same two the dist builder packages
# (scripts/build-shared-host-dist.mjs): flat, which is what make:site generates —
# sites/<client>/<x>-site and <x>-theme — and the older nested one, sites/<client>/plugins/* and
# sites/<client>/themes/*. Nothing else in a site directory is linked.
function Get-ClientSiteLinks {
    param([string]$SitesRoot)
    if (-not (Test-Path $SitesRoot)) { return }

    foreach ($site in Get-ChildItem $SitesRoot -Directory) {
        foreach ($entry in Get-ChildItem $site.FullName -Directory) {
            if ($entry.Name -eq 'plugins' -or $entry.Name -eq 'themes') {
                $kind = if ($entry.Name -eq 'plugins') { 'plugin' } else { 'theme' }
                Get-ChildItem $entry.FullName -Directory | ForEach-Object {
                    [pscustomobject]@{ Kind = $kind; Name = $_.Name; Target = $_.FullName }
                }
            } elseif ($entry.Name -like '*-site') {
                [pscustomobject]@{ Kind = 'plugin'; Name = $entry.Name; Target = $entry.FullName }
            } elseif ($entry.Name -like '*-theme') {
                [pscustomobject]@{ Kind = 'theme'; Name = $entry.Name; Target = $entry.FullName }
            }
        }
    }
}

Write-Host "Wiring monorepo -> wp-content:"
Set-Junction (Join-Path $themesDir 'corex') (Join-Path $Root 'theme')
# The names are kept: step 6 activates exactly these, and nothing else that is in the directory.
$frameworkPlugins = @()
Get-ChildItem (Join-Path $Root 'plugins') -Directory | ForEach-Object {
    Set-Junction (Join-Path $pluginsDir $_.Name) $_.FullName
    $frameworkPlugins += $_.Name
}
# Add-ons are WP-plugin-shaped Composer packages; junction any that contain a PHP file.
$addonsRoot = Join-Path $Root 'addons'
if (Test-Path $addonsRoot) {
    Get-ChildItem $addonsRoot -Directory -ErrorAction SilentlyContinue |
        Where-Object { Get-ChildItem $_.FullName -Filter '*.php' -ErrorAction SilentlyContinue } |
        ForEach-Object {
            Set-Junction (Join-Path $pluginsDir $_.Name) $_.FullName
            $frameworkPlugins += $_.Name
        }
}

# Client sites are linked into the single-site install and left for their owner to activate.
# Not into -Multisite: that install is the fixture the framework's multisite suite asserts
# against, and it mirrors what CI provisions, which has no client site in it.
$clientLinks = @()
if (-not $Multisite) {
    $clientLinks = @(Get-ClientSiteLinks (Join-Path $Root 'sites'))
}
if ($clientLinks.Count -gt 0) {
    Write-Host "Wiring client sites -> wp-content (linked, not activated):"
    foreach ($link in $clientLinks) {
        $linkDir = if ($link.Kind -eq 'theme') { $themesDir } else { $pluginsDir }
        Set-Junction (Join-Path $linkDir $link.Name) $link.Target
    }
}

# The browser suite's fixtures: every tests/e2e/fixtures/corex-e2e-*.php, as a must-use plugin of
# the single-site install, which is the one the suite drives. Copied, as the browser job in
# .github/workflows/ci.yml copies the same files, and copied again on every run, so a re-run is
# what refreshes a fixture that was edited. Copied rather than linked: a junction is for a
# directory, and a symbolic link to a file needs elevation.
#
# Not into -Multisite, for the reason no client site is linked there: that install mirrors
# .github/actions/provision-wordpress, which installs no must-use plugin.
#
# And not once a client site is linked. A must-use plugin is active from the moment the file is
# there, which is the one thing this script does not do to a client's install: the client-guide
# fixture would put a guide of its own on that site's Guides screen. The command is printed at
# the end instead, for whoever runs the framework's browser suite against that install.
$fixturesDir = Join-Path $Root 'tests\e2e\fixtures'
$e2eFixtures = @()
if (-not $Multisite -and (Test-Path $fixturesDir)) {
    $e2eFixtures = @(Get-ChildItem $fixturesDir -Filter 'corex-e2e-*.php' -File)
}
$copyFixtures = $e2eFixtures.Count -gt 0 -and $clientLinks.Count -eq 0
if ($copyFixtures) {
    $muPluginsDir = Join-Path $WpPath 'wp-content\mu-plugins'
    New-Item -ItemType Directory -Force -Path $muPluginsDir | Out-Null
    Write-Host "Copying browser-test fixtures -> wp-content\mu-plugins:"
    foreach ($fixture in $e2eFixtures) {
        # -ErrorAction Stop: under 'Continue' a failed copy is a red line the run carries on past.
        try   { Copy-Item $fixture.FullName -Destination $muPluginsDir -Force -ErrorAction Stop }
        catch { Fail ("Could not copy {0} into mu-plugins: {1}" -f $fixture.Name, $_.Exception.Message) }
        Write-Host ("  copied    {0}" -f $fixture.Name)
    }
}

# --- 6. Activate theme + plugins ---
# corex-core FIRST. corex-blocks and corex-config declare "Requires Plugins: corex-core", and WP-CLI
# activates in the order it is given — an alphabetical list puts both ahead of what they depend on
# and WP-CLI reports "Only activated 2 of 4 plugins". The previous comment here claimed WordPress
# resolved that order; it does not. This went unnoticed because a re-run activates whatever failed
# the first time and the exit code was never checked, so a clean run looked identical to a repaired
# one. CI on a fresh install is where it finally showed (PR #120).
#
# The Corex theme is activated unless a linked client theme is the one already active. This script
# is the one to re-run after moving the repository, and activating Corex unconditionally meant a
# re-run switched a client's site back to the framework's theme — found by running it, with the
# client theme on, while the note at the end claimed client sites were "left as they were".
$clientThemes = @($clientLinks | Where-Object { $_.Kind -eq 'theme' } | ForEach-Object { $_.Name })
$activeTheme  = & wp theme list --status=active --field=name --path="$WpPath" 2>$null | Select-Object -First 1
if ($clientThemes -contains $activeTheme) {
    Write-Host "Leaving the client theme '$activeTheme' active."
} else {
    & wp theme activate corex --path="$WpPath" | Out-Null
    if ($LASTEXITCODE -ne 0) { Fail "Could not activate the Corex theme." }
}

if ($Multisite) {
    # The network fixture the multisite suite asserts against, identical to the CI action's.
    # Network-activated plugins must load on every site; corex-email is activated on site2 ALONE,
    # which is the half of the assertion that fails if activation scope is ignored.
    $baseUrl = $SiteUrl.TrimEnd('/')

    & wp theme enable corex --network --path="$WpPath" | Out-Null
    if ($LASTEXITCODE -ne 0) { Fail "Could not network-enable the Corex theme." }

    & wp plugin activate corex-core --network --path="$WpPath" | Out-Null
    if ($LASTEXITCODE -ne 0) { Fail "Could not network-activate corex-core, which every other plugin requires." }

    & wp plugin activate corex-config corex-forms corex-blocks corex-ui corex-guides `
        --network --path="$WpPath" | Out-Null
    if ($LASTEXITCODE -ne 0) { Fail "Could not network-activate the Corex plugins." }

    # Ordered, not a set: site2 must exist and have corex-email activated on it BEFORE site3 is
    # created, because SiteSchemaTest asserts that site3's CoreX tables can only have arrived via
    # wp_initialize_site. Creating them together would still pass for the wrong reason.
    foreach ($site in @(
        @{ Slug = 'site2'; Title = 'Second Site' },
        @{ Slug = 'site3'; Title = 'Third Site'  }
    )) {
        # Held in plain variables, not read as $site.Slug inside the argument. PowerShell expands
        # only the bare variable there and appends the rest as literal text, so `--slug=$site.Slug`
        # is sent as `--slug=System.Collections.Hashtable.Slug` — which WP-CLI accepts. The first
        # pass creates a site at that path and the second fails with "Sorry, that site already
        # exists!", naming neither the real cause nor the slug it actually used.
        $slug  = $site.Slug
        $title = $site.Title

        # Idempotent, and checked on the captured output. `... | Out-Null; if (-not $?)` reads the
        # exit status of Out-Null rather than the match, so it is always true and the site is never
        # created — a re-run would then silently diverge from a fresh one.
        $existing = @(& wp site list --path="$WpPath" --field=url)
        $present  = $existing | Where-Object { $_ -like "*/$slug/*" }
        if (-not $present) {
            & wp site create --slug="$slug" --title="$title" --path="$WpPath" | Out-Null
            if ($LASTEXITCODE -ne 0) { Fail "Could not create $slug." }
        }

        if ($slug -eq 'site2') {
            & wp plugin activate corex-email --path="$WpPath" --url="$baseUrl/site2/" | Out-Null
            if ($LASTEXITCODE -ne 0) { Fail "Could not activate corex-email on site2." }
        }
    }
} else {
    & wp plugin activate corex-core --path="$WpPath" | Out-Null
    if ($LASTEXITCODE -ne 0) { Fail "Could not activate corex-core, which every other plugin requires." }

    # Then every other framework plugin that was junctioned above, add-ons included. The integration
    # suite resolves add-on services from the container, so a site with only plugins/* active is not
    # the environment those tests assume.
    #
    # By name, not `--all`. `--all` activates whatever is in the plugins directory, and since
    # spec 102 that includes a client's plugin once it has been linked — so a re-run would have
    # switched a client plugin on that its owner had left off. Naming the framework's own plugins
    # activates the same set as before in a repository with no client site, and leaves a client
    # plugin in whichever state it was in.
    $others = @($frameworkPlugins | Where-Object { $_ -ne 'corex-core' })
    & wp plugin activate $others --path="$WpPath" | Out-Null
    if ($LASTEXITCODE -ne 0) { Fail "Could not activate every Corex plugin - see 'wp plugin list --path=$WpPath'." }
}

# --- 7. Install the Corex schema ---
# SchemaSelfHeal runs only from admin or cron by design (spec 100 FR-035): a `wp plugin list` must
# not write tables. A WP-CLI activation therefore leaves them uncreated until somebody loads
# wp-admin, so the explicit tooling is called explicitly here — otherwise the integration suite
# boots WordPress from the CLI, finds no activity table, and fails somewhere that looks unrelated.
if ($Multisite) {
    & wp corex migrate --network --path="$WpPath"
} else {
    & wp corex migrate --path="$WpPath"
}
if ($LASTEXITCODE -ne 0) { Fail "wp corex migrate failed - the Corex tables were not created." }

# --- 8. Verify (the constitution's Environment Gate) ---
Write-Host "`n== Verification ==" -ForegroundColor Cyan
& wp theme list --path="$WpPath"
if ($Multisite) {
    & wp site list --path="$WpPath" --fields=blog_id,url
    & wp plugin list --network --path="$WpPath" --status=active --field=name
} else {
    & wp plugin list --path="$WpPath"
}
if ($clientLinks.Count -gt 0) {
    Write-Host "`nClient sites are linked and were left as they were. To switch one on:"
    foreach ($link in $clientLinks) {
        Write-Host ("  wp {0} activate {1} --path={2}" -f $link.Kind, $link.Name, $WpDir)
    }
}
if ($e2eFixtures.Count -gt 0 -and -not $copyFixtures) {
    $muPluginsRel = Join-Path $WpDir 'wp-content\mu-plugins'
    Write-Host "`nThe browser suite's fixtures were not copied: a must-use plugin is active as soon as it is there."
    Write-Host "To run the framework's browser suite against this install:"
    Write-Host "  New-Item -ItemType Directory -Force $muPluginsRel | Out-Null"
    Write-Host "  Copy-Item tests\e2e\fixtures\corex-e2e-*.php $muPluginsRel"
}
Write-Host "`nSite : $SiteUrl"
Write-Host "Admin: $SiteUrl/wp-admin/  ($AdminUser)"
Write-Host "Done." -ForegroundColor Green
