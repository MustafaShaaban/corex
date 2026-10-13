<?php

/**
 * A flow is read as it was written.
 *
 * WordPress takes one level of slashes off a post's title and off a meta value on their way
 * in, and the flow store handed both over as they were. A field's rule `pattern:^\d{2,4}$` was
 * stored as `pattern:^d{2,4}$`, which no longer asks for digits; a help text naming
 * `C:\offers\new` named `C:offersnew` (measured 2026-10-13, DECISIONS #331).
 *
 * On real WordPress, through the store and through the routes the builder uses.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Forms\Flow\FlowController;
use Corex\Forms\Flow\WpFlowStore;
use Corex\Forms\Submission\FlowSubmissionController;
use Corex\Tests\Support\BackslashedText;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\MailDelivery;
use Corex\Tests\Support\NotificationRows;
use Corex\Tests\Support\WatchedTransients;

function keptFlowSlug(): string
{
    return 'kept-' . strtolower(wp_generate_password(10, false));
}

function keptFlowRequest(string $method, string $route, array $payload = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_body_params($payload);

    return $request;
}

beforeEach(function () {
    $this->store = Boot::app()->container()->make(WpFlowStore::class);
    $this->store->registerPostType();
    $this->flows = CreatedPosts::watch(WpFlowStore::POST_TYPE, 'corex_submission');
    // A submission is counted by the rate limiter, and tells whoever manages submissions.
    $this->transients = WatchedTransients::watch('corex_throttle_');
    $this->mailSettings = MailDelivery::answerAs(MailDelivery::CAPTURE);
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
});

afterEach(function () {
    $this->flows->delete();
    $this->transients->restore();
    NotificationRows::forgetPrefixed('submission.new:kept-');
    MailDelivery::stop($this->mailSettings);
});

it('keeps the name and the content of a record as they were handed over', function (string $text) {
    $payload = ['text' => $text, 'inside' => ['a list' => [$text], 'deeper' => ['still' => $text]]];

    $id = $this->store->create('store_probe', keptFlowSlug(), $text, 0, $payload);

    expect($this->store->find($id)['name'])->toBe($text)
        ->and($this->store->find($id)['payload'])->toBe($payload);
})->with(BackslashedText::cases());

it('keeps them when the record is saved again, however many times', function (string $text) {
    $id = $this->store->create('store_probe', keptFlowSlug(), 'A name', 0, ['text' => 'some text']);

    $this->store->update($id, $text, ['text' => $text]);
    $read = $this->store->find($id);
    // A flow's record is read and written back whole when its draft or its state changes.
    $this->store->update($id, $read['name'], $read['payload']);

    expect($this->store->find($id)['name'])->toBe($text)
        ->and($this->store->find($id)['payload'])->toBe(['text' => $text]);
})->with(BackslashedText::cases());

it('hands back what is not text as the kind of value it was', function () {
    // A flow's draft number is read where it is a whole number and its test mode where it is
    // a boolean: putting slashes on must not make text of either.
    $payload = ['count' => 3, 'ratio' => 1.5, 'on' => true, 'off' => false, 'nothing' => null, 'empty' => ''];

    $id = $this->store->create('store_probe', keptFlowSlug(), 'A name', 0, $payload);

    expect($this->store->find($id)['payload'])->toBe($payload);
});

it('gives the builder back a flow with its rules and its wording as they were written', function () {
    $controller = Boot::app()->container()->make(FlowController::class);
    $configuration = [
        'schema' => [[
            'uuid' => 'code-field',
            'key' => 'code',
            'label' => 'Offer code',
            'type' => 'text',
            'required' => true,
            'help_text' => 'As printed on C:\\offers\\new, or on \\\\server\\share.',
        ]],
        'validation' => ['code' => ['required', 'pattern:^\\d{2,4}$']],
        'routing' => ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]],
        'email_routes' => [],
        'success' => ['type' => 'inline', 'message' => 'Received \\o/'],
        'placement_snapshot' => ['type' => 'block'],
    ];

    $created = $controller->create(keptFlowRequest('POST', '/corex/v1/flows', [
        'slug' => keptFlowSlug(),
        'name' => 'Offers \\ returns',
        'owner_id' => get_current_user_id(),
        'placement_type' => 'block',
        'configuration' => $configuration,
    ]));
    $flowId = $created->get_data()['data']['flow']['id'];
    $show = keptFlowRequest('GET', '/corex/v1/flows/' . $flowId);
    $show->set_url_params(['id' => $flowId]);
    $shown = $controller->show($show)->get_data()['data'];
    $draft = $shown['versions'][0]['configuration'];

    expect($shown['flow']['name'])->toBe('Offers \\ returns')
        ->and($draft['validation'])->toBe($configuration['validation'])
        ->and($draft['schema'][0]['help_text'])->toBe($configuration['schema'][0]['help_text'])
        ->and($draft['success']['message'])->toBe('Received \\o/')
        // The checksum a later save is checked against is the one of the content as written.
        ->and($shown['versions'][0]['checksum'])->toBe($created->get_data()['data']['version']['checksum']);
});

it('takes the answer a published flow\'s pattern asks for, and refuses another', function () {
    // Stored without its backslash the rule asked for the letter d, two to four times.
    $flows = Boot::app()->container()->make(FlowController::class);
    $created = $flows->create(keptFlowRequest('POST', '/corex/v1/flows', [
        'slug' => keptFlowSlug(),
        'name' => 'Offer codes',
        'owner_id' => get_current_user_id(),
        'placement_type' => 'block',
        'configuration' => [
            'schema' => [['uuid' => 'code-field', 'key' => 'code', 'label' => 'Offer code', 'type' => 'text', 'required' => true]],
            'validation' => ['code' => ['required', 'pattern:^\\d{2,4}$']],
            'routing' => ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]],
            'email_routes' => [],
            'success' => ['type' => 'inline', 'message' => 'Received.'],
            'placement_snapshot' => ['type' => 'block'],
        ],
    ]));
    $flowId = $created->get_data()['data']['flow']['id'];
    $publish = keptFlowRequest('POST', '/corex/v1/flows/' . $flowId . '/publish', ['expected_version' => 1]);
    $publish->set_url_params(['id' => $flowId]);
    $flows->publish($publish);
    $visitor = Boot::app()->container()->make(FlowSubmissionController::class);
    $answer = static function (string $code) use ($flowId, $visitor): WP_REST_Response {
        $submit = keptFlowRequest('POST', '/corex/v1/flows/' . $flowId . '/submit', ['code' => $code]);
        $submit->set_url_params(['id' => $flowId]);

        return $visitor->submit($submit);
    };

    expect($answer('2026')->get_status())->toBe(200)
        ->and($answer('dddd')->get_status())->toBe(422);
});
