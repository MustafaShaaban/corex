<?php

/**
 * Loads a generated site plugin as WordPress would, in a PHP process of its own, and says what it did.
 *
 * Usage: php boot-site-plugin.php <plugin file> <scenario>
 *
 * Scenarios (spec 135, FR-030 and FR-031):
 * - `booted`: CoreX has booted before `plugins_loaded` reaches the site plugin.
 * - `later`: CoreX boots after the site plugin's `plugins_loaded` callback has run.
 * - `included-first`: the plugin file is included before any CoreX class exists (WordPress includes
 *   `acme-site` before `corex-core`), then CoreX loads and boots on `plugins_loaded`.
 * - `loaded-late`: the plugin file is loaded after `plugins_loaded` (activated in this request).
 * - `absent`: corex-core is not active, so no CoreX class exists.
 * - `no-block-folder`: CoreX is up and its container does not offer the block-folder service.
 *
 * In every scenario `corex_booted` fires a second time, then `init` once. Prints one line of JSON: how often the
 * provider booted (the `init` callbacks it added), how often before CoreX had booted, the block-folder
 * calls, and what the provider asked the container for.
 *
 * @package Corex\Tests\Fixtures\Cli
 */

declare(strict_types=1);

[, $pluginFile, $scenario] = $argv;

define('ABSPATH', __DIR__ . '/');

/** @var array<string, list<array{callback: callable, priority: int, args: int}>> $hooks */
$hooks = [];

/** @var array<string, int> $fired */
$fired = [];

function add_action(string $hook, callable $callback, int $priority = 10, int $args = 1): bool
{
    global $hooks;

    $hooks[$hook][] = ['callback' => $callback, 'priority' => $priority, 'args' => $args];

    return true;
}

function did_action(string $hook): int
{
    global $fired;

    return $fired[$hook] ?? 0;
}

function do_action(string $hook, mixed ...$arguments): void
{
    global $hooks, $fired;

    $fired[$hook] = ($fired[$hook] ?? 0) + 1;
    $callbacks    = $hooks[$hook] ?? [];
    usort($callbacks, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

    foreach ($callbacks as $entry) {
        ($entry['callback'])(...array_slice($arguments, 0, $entry['args']));
    }
}

function initCallbacks(): int
{
    global $hooks;

    return count($hooks['init'] ?? []);
}

$container = null;
$app       = null;

// What corex-core's file does when WordPress includes it: its classes exist from then on.
$loadCorex = static function () use ($scenario, &$container, &$app): void {
    if ($scenario === 'absent') {
        return;
    }

    require_once __DIR__ . '/site-plugin-corex.php';

    $container = new Corex\Container\FakeContainer($scenario !== 'no-block-folder');
    $app       = new Corex\Foundation\Application($container);
};
$bootCore = static function () use (&$app): void {
    if ($app !== null) {
        Corex\Support\Facades\Corex::$app = $app;
    }

    do_action('corex_booted', $app);
};

if ($scenario === 'included-first') {
    // WordPress includes acme-site before corex-core (alphabetically), and CoreX boots on plugins_loaded.
    require $pluginFile;
    $loadCorex();
    add_action('plugins_loaded', $bootCore, 10);
    do_action('plugins_loaded');
    $beforeCorex = 0;
} elseif ($scenario === 'later') {
    $loadCorex();
    require $pluginFile;
    do_action('plugins_loaded');
    $beforeCorex = initCallbacks();
    $bootCore();
} elseif ($scenario === 'loaded-late') {
    $loadCorex();
    $bootCore();
    do_action('plugins_loaded');
    $beforeCorex = 0;
    require $pluginFile;
} else {
    $loadCorex();
    $bootCore();
    $beforeCorex = 0;
    require $pluginFile;
    do_action('plugins_loaded');
}

// A second signal must not boot the provider again.
do_action('corex_booted', $app);
do_action('init');

echo json_encode([
    'boots'       => initCallbacks(),
    'beforeCorex' => $beforeCorex,
    'blockFolder' => class_exists(Corex\Blocks\BlockFolder::class, false) ? Corex\Blocks\BlockFolder::$calls : [],
    'singletons'  => $container?->singletons ?? [],
    'made'        => $container?->made ?? [],
]) . "\n";
