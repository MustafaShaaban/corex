<?php

/**
 * A function WordPress loads only in its admin is loaded by the file that calls it.
 *
 * `wp_handle_upload()`, `dbDelta()`, `deactivate_plugins()` and their neighbours live under
 * `wp-admin/includes/`, which WordPress reads for an admin page and for nothing else. On the front
 * end and on a REST request they do not exist unless somebody loaded them.
 *
 * The attachment store called `wp_handle_upload()` without loading it, and every file sent through
 * a form answered 500 on a client's site (reported on 2026-10-09). It worked on the development
 * site and in CI for a reason that had nothing to do with it: another add-on creates its table on
 * every request and loads the whole admin on the way. No test with a stubbed `wp_handle_upload()`
 * can see that, so this one reads the source.
 *
 * @package Corex\Tests\Unit\Foundation
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

/**
 * The admin-only functions CoreX is known to reach for, and the file that defines each.
 *
 * `upgrade.php` and `admin.php` load every other file here, so either one satisfies any of them.
 */
const ADMIN_ONLY_FUNCTIONS = [
    'wp_handle_upload' => 'file.php',
    'wp_handle_sideload' => 'file.php',
    'download_url' => 'file.php',
    'unzip_file' => 'file.php',
    'wp_tempnam' => 'file.php',
    'WP_Filesystem' => 'file.php',
    'request_filesystem_credentials' => 'file.php',
    'wp_generate_attachment_metadata' => 'image.php',
    'wp_read_image_metadata' => 'image.php',
    'wp_crop_image' => 'image.php',
    'media_handle_upload' => 'media.php',
    'media_handle_sideload' => 'media.php',
    'media_sideload_image' => 'media.php',
    'dbDelta' => 'upgrade.php',
    'maybe_create_table' => 'upgrade.php',
    'get_plugins' => 'plugin.php',
    'get_plugin_data' => 'plugin.php',
    'is_plugin_active' => 'plugin.php',
    'is_plugin_active_for_network' => 'plugin.php',
    'activate_plugin' => 'plugin.php',
    'activate_plugins' => 'plugin.php',
    'deactivate_plugins' => 'plugin.php',
    'delete_plugins' => 'plugin.php',
    'wp_delete_user' => 'user.php',
    'get_editable_roles' => 'user.php',
    'post_exists' => 'post.php',
];

/**
 * @return list<string> absolute paths of CoreX's own PHP source
 */
function corexPhpSourceFiles(): array
{
    $files = [];

    foreach (['plugins', 'addons', 'packages', 'theme'] as $relativeRoot) {
        $root = ThemeContract::root() . '/' . $relativeRoot;
        if (! is_dir($root)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            $isOurs = ! str_contains($path, '/vendor/') && ! str_contains($path, '/node_modules/');

            if ($file->isFile() && $file->getExtension() === 'php' && $isOurs) {
                $files[] = $path;
            }
        }
    }

    return $files;
}

/**
 * The admin-only functions a file calls, read from its tokens so a comment or a string that
 * names one is not a call.
 *
 * @return list<string>
 */
function adminOnlyFunctionsCalledIn(string $source): array
{
    $tokens = array_values(array_filter(
        token_get_all($source),
        static fn (array|string $token): bool => ! is_array($token)
            || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
    ));
    $called = [];

    foreach ($tokens as $index => $token) {
        // `\dbDelta(` is one token of its own kind: a name written from the root namespace.
        $name = is_array($token) && in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)
            ? ltrim($token[1], '\\')
            : '';
        if (! isset(ADMIN_ONLY_FUNCTIONS[$name])) {
            continue;
        }

        $before = $tokens[$index - 1] ?? '';
        $isAMethodOrADeclaration = is_array($before)
            && in_array($before[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true);

        if (($tokens[$index + 1] ?? '') === '(' && ! $isAMethodOrADeclaration) {
            $called[] = $name;
        }
    }

    return array_values(array_unique($called));
}

it('loads the admin file of every admin-only function it calls', function () {
    $unloaded = [];
    $root = str_replace('\\', '/', ThemeContract::root()) . '/';
    // Reading a file's tokens is the slow part, so only a file that names one of them is read.
    $namesOne = '/\b(?:' . implode('|', array_keys(ADMIN_ONLY_FUNCTIONS)) . ')\s*\(/';

    foreach (corexPhpSourceFiles() as $path) {
        $source = (string) file_get_contents($path);
        if (preg_match($namesOne, $source) !== 1) {
            continue;
        }

        foreach (adminOnlyFunctionsCalledIn($source) as $function) {
            $loaders = [ADMIN_ONLY_FUNCTIONS[$function], 'upgrade.php', 'admin.php'];
            $loads = array_filter(
                $loaders,
                static fn (string $file): bool => str_contains($source, 'wp-admin/includes/' . $file),
            );

            if ($loads === []) {
                $unloaded[] = sprintf(
                    '%s calls %s() and never loads wp-admin/includes/%s',
                    str_replace($root, '', $path),
                    $function,
                    ADMIN_ONLY_FUNCTIONS[$function],
                );
            }
        }
    }

    expect($unloaded)->toBe([]);
});

/**
 * The scan is only worth trusting if it sees a call and is not fooled by a mention.
 */
it('tells a call from a mention', function (string $source, array $called) {
    expect(adminOnlyFunctionsCalledIn('<?php ' . $source))->toBe($called);
})->with([
    'a call' => ['$moved = wp_handle_upload($file);', ['wp_handle_upload']],
    'a call in a namespace, fully qualified' => ['\dbDelta($sql);', ['dbDelta']],
    'a comment naming one' => ['// wp_handle_upload() checks the file.' . "\n" . '$a = 1;', []],
    'a string naming one' => ['$name = "wp_handle_upload()";', []],
    'a method of the same name' => ['$store->download_url($id);', []],
    'a static method of the same name' => ['Files::unzip_file($zip);', []],
    'a function of our own being declared' => ['function post_exists(int $id): bool { return true; }', []],
    'an existence check, which is not a call' => ['if (function_exists("activate_plugins")) { $a = 1; }', []],
]);
