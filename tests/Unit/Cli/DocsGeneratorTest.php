<?php

/**
 * Unit tests for the docs:generate reader + renderer: extract a class's namespace,
 * summary, and public method signatures from source (no class loading) and render
 * Starlight Markdown.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Docs\ClassDocReader;
use Corex\Cli\Docs\DocsGenerator;
use Corex\Cli\Docs\MarkdownDocRenderer;

function fixtureClassFile(): string
{
    $code = <<<'PHP'
<?php
namespace Acme\Demo;

/**
 * A demo widget.
 *
 * @package Acme
 */
final class Widget
{
    /** Build the thing. */
    public function build(int $count, ?string $label = null): string
    {
        return '';
    }

    public function hidden(): void
    {
    }

    private function secret(): void
    {
    }
}
PHP;
    $file = tempnam(sys_get_temp_dir(), 'corex_doc_') . '.php';
    file_put_contents($file, $code);

    return $file;
}

it('reads a class into a ClassDoc with namespace, summary, and public methods', function () {
    $doc = (new ClassDocReader())->read(fixtureClassFile());

    expect($doc)->not->toBeNull();
    expect($doc->fqcn)->toBe('Acme\\Demo\\Widget');
    expect($doc->shortName)->toBe('Widget');
    expect($doc->kind)->toBe('class');
    expect($doc->summary)->toBe('A demo widget.');

    // Only the public methods, in order — never the private one.
    expect($doc->methods)->toHaveCount(2);
    expect($doc->methods[0]['signature'])->toBe('build(int $count, ?string $label): string');
    expect($doc->methods[0]['summary'])->toBe('Build the thing.');
    expect($doc->methods[1]['signature'])->toBe('hidden(): void');
});

it('returns null for a file with no named class', function () {
    $file = tempnam(sys_get_temp_dir(), 'corex_doc_') . '.php';
    file_put_contents($file, "<?php\n// just a comment\n\$x = 1;\n");

    expect((new ClassDocReader())->read($file))->toBeNull();
});

it('renders a ClassDoc to Starlight markdown', function () {
    $doc = (new ClassDocReader())->read(fixtureClassFile());
    $md = (new MarkdownDocRenderer())->render($doc, 'Core');

    expect($md)
        ->toContain('title: "Widget"')
        ->toContain('description: "A demo widget."')
        ->toContain('`Acme\\Demo\\Widget` · class · *Core layer*')
        ->toContain('## Public API')
        ->toContain('### `build(int $count, ?string $label): string`')
        ->toContain('Build the thing.');
});

it('generates a page per class into a layer subfolder', function () {
    $reader = new ClassDocReader();
    $renderer = new MarkdownDocRenderer();
    $srcDir = sys_get_temp_dir() . '/corex_docsrc_' . uniqid('', true);
    mkdir($srcDir);
    copy(fixtureClassFile(), $srcDir . '/Widget.php');

    $out = sys_get_temp_dir() . '/corex_docout_' . uniqid('', true);
    $written = (new DocsGenerator($reader, $renderer))->generate(['Core' => $srcDir], $out);

    expect($written)->toHaveCount(1);
    expect(is_file($out . '/core/widget.md'))->toBeTrue();
});
