<?php

/**
 * A new flow starts with the emails CoreX ships, and a template bound to a flow's email may use
 * only what that email supplies unless the binding confirms it (spec 120, stories 4 and 5).
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Flow\DefaultFlowEmails;
use Corex\Forms\Flow\FlowConfiguration;
use Corex\Forms\Submission\FlowEmailVariables;
use Corex\Mail\MailTemplateCatalog;

beforeEach(function () {
    Functions\when('get_option')->justReturn('admin@example.com');
    Functions\when('is_email')->alias(static fn (string $address): string|false => filter_var($address, FILTER_VALIDATE_EMAIL) === false ? false : $address);
});

function flowTemplates(array $templates): MailTemplateCatalog
{
    return new class($templates) implements MailTemplateCatalog {
        public function __construct(private readonly array $list)
        {
        }

        public function templates(): array
        {
            return $this->list;
        }
    };
}

function flowWithFields(string ...$keys): FlowConfiguration
{
    return new FlowConfiguration(
        array_map(static fn (string $key): array => ['key' => $key, 'label' => ucfirst($key), 'type' => $key === 'cv' ? 'file' : 'text'], $keys),
        [],
        [],
        [],
        [],
        [],
    );
}

it('binds the admin notification to the site\'s administrator and the confirmation to the form\'s address', function () {
    $bindings = (new DefaultFlowEmails(flowTemplates([
        ['id' => 4, 'slug' => 'admin-notification', 'name' => 'Admin notification'],
        ['id' => 5, 'slug' => 'contact-confirmation', 'name' => 'Contact confirmation'],
    ])))->bindings();

    expect($bindings)->toBe([
        ['event' => 'team_notification', 'enabled' => true, 'template_id' => 4, 'recipient' => 'admin@example.com', 'reply_to' => 'email'],
        ['event' => 'submitter_confirmation', 'enabled' => true, 'template_id' => 5, 'recipient' => 'email', 'reply_to' => ''],
    ]);
});

it('binds nothing where the defaults are not active, or CoreX Mail is off', function () {
    expect((new DefaultFlowEmails(flowTemplates([])))->bindings())->toBe([])
        ->and((new DefaultFlowEmails(null))->bindings())->toBe([]);
});

it('says which of a template\'s variables a flow\'s email does not supply', function () {
    $configuration = flowWithFields('name', 'email', 'cv');

    expect(FlowEmailVariables::missing(
        ['submission.name', 'form.answers', 'site.name', 'submission.nickname', 'user.name', 'failure.message'],
        $configuration,
        'team_notification',
    ))->toBe(['submission.nickname', 'user.name', 'failure.message'])
        ->and(FlowEmailVariables::missing(['failure.message'], $configuration, 'admin_failure'))->toBe([]);
});

it('lists every answer with its field\'s label in the form\'s order, a file as a link to the submission', function () {
    $answers = FlowEmailVariables::answers(
        flowWithFields('email', 'name', 'cv'),
        ['name' => 'Sam', 'cv' => 'cv.pdf', 'email' => 'sam@example.com', 'hidden' => 'x'],
        'https://acme.test/submission/4',
    );

    expect($answers)->toBe([
        ['label' => 'Email', 'value' => 'sam@example.com'],
        ['label' => 'Name', 'value' => 'Sam'],
        ['label' => 'Cv', 'value' => 'cv.pdf', 'url' => 'https://acme.test/submission/4'],
    ])
        ->and(FlowEmailVariables::kindOf('submitter_confirmation'))->toBe('confirmation')
        ->and(FlowEmailVariables::kindOf('team_notification'))->toBe('notification');
});
