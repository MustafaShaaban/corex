<?php

/**
 * Two visitors sending the last allowed response at the same moment: one is stored, the other is
 * told the form is closed, and the count never passes the cap (spec 122: US-VIS-02, FR-030).
 *
 * The two submissions run in two PHP processes against the same database, started together.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Forms\Flow\FlowAvailability;
use Corex\Forms\Flow\FlowConfiguration;
use Corex\Forms\Flow\FlowService;
use Corex\Forms\Flow\FlowTransition;
use Corex\Forms\Flow\NewFlowDraft;
use Corex\Forms\Flow\WpFlowStore;
use Corex\Forms\Submission\SubmissionRepository;
use Corex\Tests\Support\MailDelivery;

/** Starts one submission in its own process; it waits until `$startAt` before sending. */
function startCappedSubmission(int $flowId, string $email, float $startAt): array
{
    $script = tempnam(sys_get_temp_dir(), 'corex-cap-') . '.php';
    file_put_contents($script, sprintf(<<<'PHP'
        <?php
        $_SERVER['HTTP_HOST'] = %1$s;
        $_SERVER['REQUEST_URI'] = '/';
        $GLOBALS['wp_filter'] = [
            'pre_option_corex_app_env' => [10 => [['function' => static fn () => 'development', 'accepted_args' => 0]]],
        ];
        define('WP_USE_THEMES', false);
        require %2$s;
        $service = \Corex\Boot::app()->container()->make(\Corex\Forms\Submission\FlowVisitorSubmissionService::class);
        time_sleep_until(%3$s);
        try {
            $result = $service->submit(%4$d, ['email' => %5$s]);
            echo 'COREX_CAP:' . ($result->pipeline->completed ? 'stored' : 'failed');
        } catch (\Corex\Forms\Flow\FlowClosedException $closed) {
            echo 'COREX_CAP:closed';
        }
        PHP,
        var_export((string) wp_parse_url(home_url(), PHP_URL_HOST) . (wp_parse_url(home_url(), PHP_URL_PORT) ? ':' . wp_parse_url(home_url(), PHP_URL_PORT) : ''), true),
        var_export(ABSPATH . 'wp-load.php', true),
        var_export($startAt, true),
        $flowId,
        var_export($email, true),
    ));
    $process = proc_open([PHP_BINARY, '-d', 'memory_limit=512M', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    return ['process' => $process, 'pipes' => $pipes, 'script' => $script];
}

/** @param array{process:resource,pipes:array<int,resource>,script:string} $started */
function finishCappedSubmission(array $started): string
{
    $stdout = (string) stream_get_contents($started['pipes'][1]);
    $stderr = (string) stream_get_contents($started['pipes'][2]);
    fclose($started['pipes'][1]);
    fclose($started['pipes'][2]);
    proc_close($started['process']);
    unlink($started['script']);

    return preg_match('/COREX_CAP:(\w+)/', $stdout, $found) === 1 ? $found[1] : 'no answer: ' . $stdout . $stderr;
}

beforeEach(function () {
    Boot::app()->container()->make(WpFlowStore::class)->registerPostType();
    $this->mail = MailDelivery::answerAs(MailDelivery::CAPTURE);
});

afterEach(function () {
    MailDelivery::stop($this->mail);
    foreach (get_posts(['post_type' => 'corex_submission', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => 'corex_flow_id', 'meta_value' => $this->flowId]) as $id) {
        wp_delete_post((int) $id, true);
    }
    foreach (get_posts(['post_type' => WpFlowStore::POST_TYPE, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1]) as $id) {
        if ((int) $id === $this->flowId || (int) wp_get_post_parent_id((int) $id) === $this->flowId) {
            wp_delete_post((int) $id, true);
        }
    }
});

it('stores one of two simultaneous last responses and tells the other the form is closed', function () {
    $flows = Boot::app()->container()->make(FlowService::class);
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $owner = (int) ($administrators[0] ?? 1);
    $now = new DateTimeImmutable();
    $created = $flows->create(new NewFlowDraft(
        slug: 'cap-race-' . strtolower(wp_generate_password(6, false)),
        name: 'Cap race',
        description: '',
        ownerId: $owner,
        placementType: 'none',
        placementId: null,
        testMode: false,
        configuration: new FlowConfiguration(
            schema: [['uuid' => 'f-email', 'key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true]],
            validation: ['email' => ['required', 'email']],
            routing: ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]],
            emailRoutes: [],
            success: ['type' => 'inline', 'message' => 'Received.'],
            placementSnapshot: ['type' => 'none'],
        ),
        actorId: $owner,
        occurredAt: $now,
    ));
    $this->flowId = $created['flow']->id;
    $flows->publish(new FlowTransition($this->flowId, 1, $owner, $now));
    $flows->saveAvailability($this->flowId, new FlowAvailability(maxResponses: 1), $owner, $now);

    $startAt = microtime(true) + 4.0;
    $first = startCappedSubmission($this->flowId, 'first@example.test', $startAt);
    $second = startCappedSubmission($this->flowId, 'second@example.test', $startAt);
    $answers = [finishCappedSubmission($first), finishCappedSubmission($second)];
    sort($answers);

    expect($answers)->toBe(['closed', 'stored'])
        ->and(Boot::app()->container()->make(SubmissionRepository::class)->responses($this->flowId))->toBe(1);
});
