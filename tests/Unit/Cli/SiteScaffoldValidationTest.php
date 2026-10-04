<?php

/**
 * Unit tests for spec 055 make:site scaffold validation.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Release\ReadinessFinding;
use Corex\Cli\Site\SiteScaffolder;
use Corex\Cli\Site\SiteScaffoldValidator;

it('validates minimal client scaffolds for isolation, identity, governance, specs, and tokens', function () {
    $base = sys_get_temp_dir() . '/corex_validation_' . uniqid('', true);
    mkdir($base);

    (new SiteScaffolder(new StubRenderer(), dirname(__DIR__, 3) . '/packages/cli/stubs'))
        ->scaffold('Acme', $base, ['minimal' => true]);

    $finding = (new SiteScaffoldValidator())->validate($base, 'minimal');

    expect($finding->category)->toBe('make-site')
        ->and($finding->status)->toBe(ReadinessFinding::STATUS_PASS)
        ->and($finding->evidence)->toContain(
            'acme-site/acme-site.php',
            'acme-theme/theme.json',
            'AGENTS.md',
            'CLAUDE.md',
            'PROGRESS.md',
            'DECISIONS.md',
            'specs/.gitkeep',
            'docs/.gitkeep',
            'namespace:AcmeSite\\',
            'css-prefix:--acme-',
            'option-prefix:acme_',
            'token-strategy:acme-theme/theme.json',
        );
});

it('validates starter scaffolds for the removable example slice and theme asset strategy', function () {
    $base = sys_get_temp_dir() . '/corex_validation_starter_' . uniqid('', true);
    mkdir($base);

    (new SiteScaffolder(new StubRenderer(), dirname(__DIR__, 3) . '/packages/cli/stubs'))
        ->scaffold('Acme', $base, ['starter' => true]);

    $finding = (new SiteScaffoldValidator())->validate($base, 'starter');

    expect($finding->status)->toBe(ReadinessFinding::STATUS_PASS)
        ->and($finding->evidence)->toContain(
            'starter:acme-site/src/Controllers/ExampleController.php',
            'starter:acme-site/src/Blocks/example/block.json',
            'starter:acme-site/tests/ExampleTest.php',
            'starter:acme-site/REMOVE-EXAMPLE.md',
            'starter:acme-theme/package.json',
            'starter:acme-theme/functions.php',
            'starter:acme-theme/assets/src/scss/main.scss',
            'starter:acme-theme/assets/src/js/main.js',
        );
});

it('fails validation when required scaffold files are missing or placeholders remain unresolved', function () {
    $base = sys_get_temp_dir() . '/corex_validation_broken_' . uniqid('', true);
    mkdir($base);

    (new SiteScaffolder(new StubRenderer(), dirname(__DIR__, 3) . '/packages/cli/stubs'))
        ->scaffold('Acme', $base);
    unlink($base . '/AGENTS.md');
    file_put_contents($base . '/acme-theme/theme.json', '{{ unresolved }}');

    $finding = (new SiteScaffoldValidator())->validate($base, 'minimal');

    expect($finding->status)->toBe(ReadinessFinding::STATUS_FAIL)
        ->and($finding->blocking)->toBeTrue()
        ->and($finding->evidence)->toContain(
            'missing:AGENTS.md',
            'unresolved-placeholder:acme-theme/theme.json',
        );
});

it('does not mistake the nested attributes of a block for an unresolved placeholder', function () {
    // spec 101. A block with an object attribute ends its comment in `}}`, and the generated
    // coming-soon template has several. The validator used to flag any `{{` or `}}` at all,
    // which no template with a real layout could pass. A placeholder is what the renderer
    // would have filled in: two braces, a name, two braces.
    $base = sys_get_temp_dir() . '/corex_validation_blocks_' . uniqid('', true);
    mkdir($base);

    (new SiteScaffolder(new StubRenderer(), dirname(__DIR__, 3) . '/packages/cli/stubs'))
        ->scaffold('Acme', $base);
    file_put_contents(
        $base . '/acme-theme/templates/page.html',
        '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"></div><!-- /wp:group -->',
    );

    $finding = (new SiteScaffoldValidator())->validate($base, 'minimal');

    expect($finding->status)->toBe(ReadinessFinding::STATUS_PASS)
        ->and(file_get_contents($base . '/acme-theme/templates/coming-soon.html'))->toContain('}}');
});

it('still catches a placeholder left in a file beside nested block attributes', function () {
    $base = sys_get_temp_dir() . '/corex_validation_mixed_' . uniqid('', true);
    mkdir($base);

    (new SiteScaffolder(new StubRenderer(), dirname(__DIR__, 3) . '/packages/cli/stubs'))
        ->scaffold('Acme', $base);
    file_put_contents(
        $base . '/acme-theme/templates/page.html',
        '<!-- wp:group {"layout":{"type":"constrained"}} --><p>{{ name }}</p><!-- /wp:group -->',
    );

    $finding = (new SiteScaffoldValidator())->validate($base, 'minimal');

    expect($finding->status)->toBe(ReadinessFinding::STATUS_FAIL)
        ->and($finding->evidence)->toContain('unresolved-placeholder:acme-theme/templates/page.html');
});
