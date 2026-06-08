<?php

/**
 * Unit tests for BootLogger (spec FR-023, SC-008).
 *
 * @package Corex\Tests\Unit\Foundation
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Support\BootLogger;

it('records warning and error messages with their levels', function () {
    $logger = new BootLogger(debug: false);

    $logger->warning('env malformed');
    $logger->error('boot failed');

    expect($logger->messages())->toHaveCount(2)
        ->and($logger->messages()[0])->toMatchArray(['level' => 'warning', 'message' => 'env malformed'])
        ->and($logger->messages()[1]['level'])->toBe('error');
});

it('hooks an admin notice exactly once when WP_DEBUG is on, regardless of message count', function () {
    Functions\expect('add_action')->once()->with('admin_notices', \Mockery::type('array'));

    $logger = new BootLogger(debug: true);
    $logger->warning('first');
    $logger->warning('second');
});

it('never hooks an admin notice when WP_DEBUG is off', function () {
    Functions\expect('add_action')->never();

    $logger = new BootLogger(debug: false);
    $logger->error('quiet failure');
});

it('renders nothing when the current user lacks manage_options', function () {
    Functions\when('add_action')->justReturn(true);
    Functions\when('current_user_can')->justReturn(false);

    $logger = new BootLogger(debug: true);
    $logger->warning('should not show');

    ob_start();
    $logger->renderNotices();

    expect(ob_get_clean())->toBe('');
});

it('escapes notice output for capable users', function () {
    Functions\when('add_action')->justReturn(true);
    Functions\when('current_user_can')->justReturn(true);
    Functions\when('esc_html__')->returnArg(1);
    Functions\expect('esc_html')->atLeast()->once()->andReturnUsing(fn ($v) => htmlspecialchars((string) $v));

    $logger = new BootLogger(debug: true);
    $logger->warning('<script>x</script>');

    ob_start();
    $logger->renderNotices();
    $html = ob_get_clean();

    expect($html)->toContain('is-dismissible')
        ->and($html)->toContain('&lt;script&gt;')
        ->and($html)->not->toContain('<script>x</script>');
});

it('never throws from a logging call', function () {
    Functions\when('add_action')->justReturn(true);

    $logger = new BootLogger(debug: true);

    expect(fn () => $logger->warning('safe'))->not->toThrow(\Throwable::class);
});
