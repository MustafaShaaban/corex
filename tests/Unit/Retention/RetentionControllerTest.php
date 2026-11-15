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

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Corex\Access\CorexAbility;
use Corex\Activity\ActivityService;
use Corex\Config\Retention\RetentionController;
use Corex\Config\Retention\RetentionSettings;
use Corex\Config\Retention\SubmissionRetention;
use Corex\Config\Retention\SubmissionRetentionStore;
use Corex\Config\Retention\SubmissionRetentionTrash;
use Corex\Config\Submissions\SubmissionAccessPolicy;
use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionTimelineStore;
use Corex\Config\Submissions\SubmissionTrashRetention;
use Corex\Config\Submissions\SubmissionTrashService;
use Corex\Config\Submissions\SubmissionWorkflowStore;
use Corex\Security\Admin\AdminGuard;
use Corex\Tests\Support\InMemorySubmissionTrash;
use Corex\Tests\Support\RecordingActivityRepository;
use Corex\Tests\Support\RecordingSubmissionEmailRecords;

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

    try {
        retentionController()->prune();
    } catch (RuntimeException $redirect) {
        parse_str((string) parse_url($redirect->getMessage(), PHP_URL_QUERY), $query);

        return $query;
    }

    throw new LogicException('The prune handler returned without redirecting.');
}

/** The handler with the real guard and retention service, and only WordPress stubbed. */
function retentionController(): RetentionController
{
    $settings   = new RetentionSettings();
    $trash      = new InMemorySubmissionTrash(new stdClass());
    $controller = new RetentionController(
        new AdminGuard(),
        new SubmissionRetention(
            $settings,
            Mockery::mock(SubmissionRetentionStore::class),
            Mockery::mock(SubmissionRetentionTrash::class),
        ),
        $settings,
        // The trash's own window is saved by the same form. A prune does not touch it.
        new SubmissionTrashRetention(
            $trash,
            new SubmissionTrashService(
                Mockery::mock(SubmissionWorkflowStore::class),
                $trash,
                Mockery::mock(SubmissionTimelineStore::class),
                new ActivityService(new RecordingActivityRepository()),
                new RecordingSubmissionEmailRecords(),
            ),
            $settings,
        ),
        // Whoever is signed in may see every submission. What a narrower person may move is the
        // trash service's to decide, and is tested there.
        new class() implements SubmissionAccessPolicy {
            public function scopeFor(int $actorId): ?SubmissionAccessScope
            {
                return new SubmissionAccessScope($actorId, true);
            }
        },
    );

    return $controller;
}

beforeEach(function () {
    Functions\when('current_user_can')->justReturn(true);
    Functions\when('get_current_user_id')->justReturn(7);
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

/*
 * Each retention form's handler checks what the screen checks before drawing the form (spec 117,
 * FR-004): saving the policy needs "Manage settings", applying it "Run dangerous actions". A refusal
 * answers 403 and is recorded under Submissions. `refuse()` ends in `exit` too, so the
 * `status_header` stub throws the status it was given.
 */
function holdingOnly(string ...$abilities): void
{
    Functions\when('current_user_can')->alias(static fn (string $cap): bool => in_array($cap, $abilities, true));
    Functions\when('is_user_logged_in')->justReturn(true);
    Functions\when('status_header')->alias(static fn (int $code) => throw new DomainException('refused ' . $code));
}

it('refuses to apply the policy without "Run dangerous actions", and records it under Submissions (FR-004)', function () {
    holdingOnly(CorexAbility::MANAGE_SETTINGS, CorexAbility::MANAGE_SUBMISSIONS);
    Actions\expectDone('corex_access_refused')
        ->once()
        ->with('action', 'corex-submissions', CorexAbility::RUN_DANGEROUS_ACTIONS);

    expect(fn () => pruneRedirectQuery(['corex_retention_action' => 'trash']))->toThrow(DomainException::class, 'refused 403');
});

it('refuses to save the policy without "Manage settings", and records it under Submissions (FR-004)', function () {
    holdingOnly(CorexAbility::RUN_DANGEROUS_ACTIONS, CorexAbility::MANAGE_SUBMISSIONS);
    Actions\expectDone('corex_access_refused')
        ->once()
        ->with('action', 'corex-submissions', CorexAbility::MANAGE_SETTINGS);
    $_POST = [RetentionController::NONCE => 'nonce-value', 'corex_retention_days' => '30'];

    expect(fn () => retentionController()->save())->toThrow(DomainException::class, 'refused 403');
});

it('applies the policy for the holder of "Run dangerous actions" (FR-004)', function () {
    holdingOnly(CorexAbility::RUN_DANGEROUS_ACTIONS);

    expect(pruneRedirectQuery(['corex_retention_action' => 'trash'])['corex_status'] ?? null)->toBe('retention-pruned');
});
