<?php
/**
 * Proves that a built shared-host package can load the framework (DECISIONS #267).
 *
 * `verifyDist()` runs this in a PHP process of its own, so nothing of the checkout that built the
 * package is loaded: no autoloader and no class. It then does what WordPress does on the host,
 * which is to include the core plugin's main file, and reports three things. Whether that file
 * loaded the autoloader the package names (`autoload.file` in `corex-release.json`). Whether one
 * class from every namespace the package says it maps (`autoload.psr4`) loads from the packaged
 * directory it is mapped to. And whether anything was loaded from outside the package.
 *
 *   php scripts/shared-host-dist-probe.php <dist directory>
 *
 * Prints one JSON object, `{"errors": [...], "loaded": {"<namespace prefix>": "<class>"}}`, and
 * exits 1 when `errors` is not empty.
 */

declare(strict_types=1);

const COREX_DIST_PROBE_CORE_PLUGIN = 'wp-content/plugins/corex-core/corex-core.php';

// The two WordPress functions corex-core.php calls while it is included. Nothing else of
// WordPress exists here, so a class that extends a WordPress class cannot load and is passed over.
function plugin_dir_path(string $file): string
{
    return dirname($file) . '/';
}

function add_action(mixed ...$arguments): bool
{
    return true;
}

function corex_dist_probe_path(string $path): string
{
    $real = realpath($path);

    return str_replace('\\', '/', $real === false ? $path : $real);
}

/**
 * What `corex-release.json` says about the autoloader, or null when it says nothing usable.
 *
 * @return array{file: string, psr4: array<string, string>}|null
 */
function corex_dist_probe_autoload(string $dist): ?array
{
    $file = $dist . '/corex-release.json';
    $manifest = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    $autoload = is_array($manifest) ? ($manifest['autoload'] ?? null) : null;
    $usable = is_array($autoload) && is_string($autoload['file'] ?? null) && ! empty($autoload['psr4']);

    return $usable ? $autoload : null;
}

/**
 * The class, interface, trait or enum a file declares under PSR-4, or null when it declares none.
 */
function corex_dist_probe_declared_type(string $file, string $prefix, string $directory): ?string
{
    $relative = substr(corex_dist_probe_path($file), strlen($directory) + 1, -strlen('.php'));
    $type = $prefix . str_replace('/', '\\', $relative);
    $separator = (int) strrpos($type, '\\');
    $namespace = preg_quote(substr($type, 0, $separator), '/');
    $name = preg_quote(substr($type, $separator + 1), '/');
    $source = (string) file_get_contents($file);

    $declaresIt = preg_match('/^namespace\s+' . $namespace . '\s*;/m', $source) === 1
        && preg_match('/^(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+' . $name . '\b/m', $source) === 1;

    return $declaresIt ? $type : null;
}

function corex_dist_probe_loads(string $type): bool
{
    try {
        return class_exists($type) || interface_exists($type) || trait_exists($type) || enum_exists($type);
    } catch (Error) {
        // Its parent or an interface is a WordPress class, which does not exist here.
        return false;
    }
}

/**
 * The first type under a directory that the packaged autoloader loads from that directory.
 */
function corex_dist_probe_first_loaded(string $prefix, string $directory): ?string
{
    $files = [];
    $tree = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($tree as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);

    foreach ($files as $file) {
        $type = corex_dist_probe_declared_type($file, $prefix, $directory);
        if ($type === null || ! corex_dist_probe_loads($type)) {
            continue;
        }
        if (corex_dist_probe_path((string) (new ReflectionClass($type))->getFileName()) === corex_dist_probe_path($file)) {
            return $type;
        }
    }

    return null;
}

/**
 * @param list<string> $errors
 * @param array<string, string> $loaded
 */
function corex_dist_probe_report(array $errors, array $loaded): never
{
    echo json_encode(['errors' => $errors, 'loaded' => (object) $loaded], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
    exit($errors === [] ? 0 : 1);
}

$dist = corex_dist_probe_path((string) ($argv[1] ?? ''));
$autoload = corex_dist_probe_autoload($dist);

if ($autoload === null) {
    corex_dist_probe_report(['corex-release.json names no autoloader and no namespace (autoload); rebuild the package'], []);
}
if (! is_file($dist . '/' . COREX_DIST_PROBE_CORE_PLUGIN)) {
    corex_dist_probe_report(['the package has no ' . COREX_DIST_PROBE_CORE_PLUGIN], []);
}

$errors = [];
$loaded = [];

define('ABSPATH', $dist . '/');
require $dist . '/' . COREX_DIST_PROBE_CORE_PLUGIN;

if (! in_array($dist . '/' . $autoload['file'], array_map('corex_dist_probe_path', get_included_files()), true)) {
    $errors[] = 'corex-core.php did not load ' . $autoload['file'] . ', so on the host every CoreX plugin stays dormant';
}

foreach ($autoload['psr4'] as $prefix => $relative) {
    $directory = corex_dist_probe_path($dist . '/' . rtrim($relative, '/'));
    $type = is_dir($directory) ? corex_dist_probe_first_loaded($prefix, $directory) : null;

    if ($type === null) {
        $errors[] = 'no class under ' . $prefix . ' loads from ' . $relative;
        continue;
    }
    $loaded[$prefix] = $type;
}

foreach (array_map('corex_dist_probe_path', get_included_files()) as $file) {
    if ($file !== corex_dist_probe_path(__FILE__) && ! str_starts_with($file, $dist . '/')) {
        $errors[] = 'loaded from outside the package: ' . $file;
    }
}

corex_dist_probe_report($errors, $loaded);
