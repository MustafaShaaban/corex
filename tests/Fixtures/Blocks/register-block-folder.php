<?php

/**
 * Registers a folder of blocks through corex-blocks' BlockFolder, in a PHP process of its own.
 *
 * Block registration needs `WP_PLUGIN_DIR` and WordPress's block-type classes. Defined in the unit
 * suite they would stay defined for every test after this one (`Boot` reads `WP_PLUGIN_DIR`), so they
 * live here, with the few WordPress functions registration calls, each writing down what it was given.
 *
 * Usage: php register-block-folder.php <built dir> <source dir> <plugins dir>
 * Prints JSON: {"registered": [block dirs], "translations": [[handle, domain]]}.
 *
 * @package Corex\Tests\Fixtures\Blocks
 */

declare(strict_types=1);

use Corex\Blocks\BlockFolder;
use Corex\Blocks\BlocksServiceProvider;
use Corex\Container\Container;
use Corex\Support\BootLogger;

[, $built, $source, $pluginsDir] = $argv;

define('ABSPATH', dirname(__DIR__, 3) . '/');
define('WP_PLUGIN_DIR', $pluginsDir);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

/** @var array{registered: list<string>, translations: list<array{0: string, 1: string}>} $seen */
$seen = ['registered' => [], 'translations' => []];

/** The fields of WordPress's block type that registration reads. */
final class WP_Block_Type
{
    public string $name = '';

    public ?string $textdomain = null;

    /** @var list<string> */
    public array $editor_script_handles = [];

    /** @var list<string> */
    public array $view_script_handles = [];

    /** @var list<string> */
    public array $script_handles = [];
}

final class WP_Block_Type_Registry
{
    private static ?self $instance = null;

    /** @var array<string, WP_Block_Type> */
    private array $types = [];

    public static function get_instance(): self
    {
        return self::$instance ??= new self();
    }

    public function is_registered(string $name): bool
    {
        return isset($this->types[$name]);
    }

    public function add(WP_Block_Type $type): void
    {
        $this->types[$type->name] = $type;
    }
}

/**
 * As WordPress does: the type is read from the folder's block.json and its editor script gets a handle
 * named after the block. Like WordPress 7.0's `register_block_type_from_metadata()`, it leaves the type's
 * `textdomain` empty: the block.json's `textdomain` is not one of the fields it copies onto the type.
 *
 * @param array<string, mixed> $args
 */
function register_block_type(string $dir, array $args = []): WP_Block_Type
{
    global $seen;

    $metadata = json_decode((string) file_get_contents($dir . '/block.json'), true);
    $type     = new WP_Block_Type();

    $type->name                  = (string) $metadata['name'];
    $type->editor_script_handles = [str_replace('/', '-', $type->name) . '-editor-script'];

    WP_Block_Type_Registry::get_instance()->add($type);
    $seen['registered'][] = str_replace('\\', '/', $dir);

    return $type;
}

function wp_set_script_translations(string $handle, string $domain = 'default', string $path = ''): bool
{
    global $seen;

    $seen['translations'][] = [$handle, $domain];

    return true;
}

$container = new Container();
$container->instance(BootLogger::class, new BootLogger(debug: false));
(new BlocksServiceProvider($container))->register();

$container->make(BlockFolder::class)->register($built, $source);

echo json_encode($seen);
