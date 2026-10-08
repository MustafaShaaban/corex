<?php
/**
 * Proves that a built shared-host package can load the framework (DECISIONS #265).
 *
 * `verifyDist()` runs this in a PHP process of its own, so nothing of the checkout that built the
 * package is loaded: no autoloader and no class. It then does what WordPress does on the host,
 * which is to include the core plugin's main file, and reports two things. Whether that file found
 * the packaged autoloader. And whether one class from every namespace the package says it maps
 * (`autoload.psr4` in `corex-release.json`) loads from the packaged directory it is mapped to.
 *
 *   php scripts/shared-host-dist-probe.php <dist directory>
 *
 * Prints one JSON object, `{"errors": [...], "loaded": {"<namespace prefix>": "<class>"}}`, and
 * exits 1 when `errors` is not empty.
 */

declare(strict_types=1);

const COREX_DIST_PROBE_CORE_PLUGIN = 'wp-content/plugins/corex-core/corex-core.php';
const COREX_DIST_PROBE_AUTOLOADER = 'wp-content/vendor/autoload.php';

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
 * The class, interface, trait or enum a file declares under PSR-4, or null when it declares none.
 */
function corex_dist_probe_declared_type(string $file, string $prefix, string $directory): ?string
{
    $relative = substr(corex_dist_probe_path($file), strlen($directory) + 1, -strlen('.php'));
    $type = $prefix . str_replace('/', '\\', $relative);
    $separator = strrpos($type, '\\');
    $namespace = preg_quote(substr($type, 0, (int) $separator), '/');
    $name = preg_quote(substr($type, (int) $separator + 1), '/');
    $source = (string) file_get_contents($file);

    $declaresIt = preg_match('/^namespace\s+' . $namespace . '\s*;/m', $source) === 1
        && preg_match('/^(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+' . $name . '\b/m', $source) === 1;

    return $declaresIt ? $type : null;
}

function corex_dist_probe_loads(string $type): bool
{
    try {
        return class_exists($type) || interface_exists($type) || trait_exists($type) || enum_exists($type);
    } catch (Throwable) {
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

$dist = corex_dist_probe_path((string) ($argv[1] ?? ''));
$errors = [];
$loaded = [];

if (! is_file($dist . '/' . COREX_DIST_PROBE_CORE_PLUGIN)) {
    $errors[] = 'the package has no ' . COREX_DIST_PROBE_CORE_PLUGIN;
} else {
    define('ABSPATH', $dist . '/');
    require $dist . '/' . COREX_DIST_PROBE_CORE_PLUGIN;

    $included = array_map('corex_dist_probe_path', get_included_files());
    if (! in_array($dist . '/' . COREX_DIST_PROBE_AUTOLOADER, $included, true)) {
        $errors[] = 'corex-core.php did not load ' . COREX_DIST_PROBE_AUTOLOADER
            . ', so on the host every CoreX plugin stays dormant';
    }
}

$manifest = json_decode((string) @file_get_contents($dist . '/corex-release.json'), true);
$namespaces = is_array($manifest) ? ($manifest['autoload']['psr4'] ?? []) : [];

if ($namespaces === []) {
    $errors[] = 'corex-release.json names no namespace to load (autoload.psr4); rebuild the package';
}

foreach ($namespaces as $prefix => $relative) {
    $directory = corex_dist_probe_path($dist . '/' . rtrim((string) $relative, '/'));
    $type = is_dir($directory) ? corex_dist_probe_first_loaded((string) $prefix, $directory) : null;

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

echo json_encode(['errors' => $errors, 'loaded' => $loaded], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
exit($errors === [] ? 0 : 1);
