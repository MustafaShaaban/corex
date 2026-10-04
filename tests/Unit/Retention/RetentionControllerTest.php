<?php

/**
 * Unit tests for what the retention prune handler tells the Submissions screen (DECISIONS #233).
 *
 * The handler redirects back to the screen, and the screen can only report what the redirect
 * carries. It carried a status and a count, so the screen could not tell an archive from an
 * anonymization and called every run a move to trash.
 *
 * Nothing CoreX is doubled except the record store, which no test here may reach: the real guard
 * and the real retention service run, with only WordPress stubbed. Retention is off (`get_option`
 * answers 0), so a run selects nothing and the store mock, having no expectations, fails any test
 * that touches a record.
 *
 * @package Corex\Tests\Unit\Retention
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Retention\RetentionController;
use Corex\Config\Retention\RetentionSettings;
use Corex\Config\Retention\SubmissionRetention;
use Corex\Config\Retention\SubmissionRetentionStore;
use Corex\Security\Admin\AdminGuard;

/**
 * Post a confirmed prune and return the query string the handler redirects with.
 *
 * `prune()` ends in `exit`, which cannot be caught and would take the runner down. So the
 * `wp_safe_redirect` stub throws the destination instead of returning: the handler runs for real
 * up to the redirect and stops there.
 *
 * @param array<string,string> $post
 * @return array<string,string>
 */
function pruneRedirectQuery(array $post): array
{
    $_POST = $post + [RetentionController::NONCE => 'nonce-value', 'corex_confirm' => '1'];

    $settings   = new RetentionSettings();
    $controller = new RetentionController(
        new AdminGuard(),
        new SubmissionRetention($settings, Mockery::mock(SubmissionRetentionStore::class)),
        $settings,
    );

    try {
        $controller->prune();
    } catch (RuntimeException $redirect) {
        parse_str((string) parse_url($redirect->getMessage(), PHP_URL_QUERY), $query);

        return $query;
    }

    throw new LogicException('The prune handler returned without redirecting.');
}

beforeEach(function () {
    Functions\when('current_user_can')->justReturn(true);
    Functions\when('wp_verify_nonce')->justReturn(1);
    Functions\when('wp_unslash')->returnArg();
    Functions\when('sanitize_text_field')->returnArg();
    Functions\when('sanitize_key')->alias(
        static fn (string $value): string => (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)),
    );
    Functions\when('get_option')->justReturn(0);
    Functions\when('admin_url')->alias(
        static fn (string $path = ''): string => 'https://example.test/wp-admin/' . $path,
    );
    Functions\when('add_query_arg')->alias(
        static fn (array $args, string $url): string => $url . '?' . http_build_query($args),
    );
    Functions\when('wp_safe_redirect')->alias(
        static fn (string $location) => throw new RuntimeException($location),
    );

    $_POST = [];
});

afterEach(function () {
    $_POST = [];
});

it('tells the screen which action ran', function (string $action) {
    $query = pruneRedirectQuery(['corex_retention_action' => $action]);

    expect($query['corex_status'])->toBe('retention-pruned')
        ->and($query['corex_action'] ?? null)->toBe($action);
})->with(['archive', 'trash', 'anonymize']);

/**
 * A post without the field runs the handler's default, which is trash — so trash is what ran and
 * what the screen must be told.
 */
it('reports a move to trash when the form posted no action', function () {
    $query = pruneRedirectQuery([]);

    expect($query['corex_action'] ?? null)->toBe('trash');
});

/**
 * The select offers three actions, and a post is whatever was sent. Anything else used to reach
 * the retention service, whose `InvalidArgumentException` nothing caught: the operator got
 * WordPress's critical-error page instead of an answer (DECISIONS #239).
 */
it('refuses an action the form does not offer, and says so instead of failing', function () {
    $query = pruneRedirectQuery(['corex_retention_action' => 'delete']);

    // The whole query: a refusal reports no action and no count, because none ran.
    expect($query)->toBe(['page' => 'corex-submissions', 'corex_status' => 'retention-invalid']);
});
