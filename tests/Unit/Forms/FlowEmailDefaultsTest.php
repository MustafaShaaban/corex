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

// Spec 122, FR-023: a variable a developer registers for flow emails is supplied by every flow.
it('counts a registered flow email variable as supplied', function () {
    expect(FlowEmailVariables::missing(['order.total', 'user.name'], flowWithFields('email'), 'team_notification', ['order.total']))
        ->toBe(['user.name']);
});

it('publishes a template that uses a variable a developer registered for flow emails', function () {
    Functions\when('__')->returnArg();
    $registry = new Corex\Forms\Flow\EmailVariableRegistry();
    $registry->register('order.total', 'Order total', static fn (): string => '10');
    $validator = new Corex\Forms\Flow\FlowConfigurationValidator(
        new Corex\Forms\Schema\FieldTypeRegistry(),
        new Corex\Forms\Validation\RuleRegistry(),
        new Corex\Forms\Success\SuccessStateRegistry(),
        flowTemplates([['id' => 4, 'slug' => 'orders', 'name' => 'Orders', 'variables' => ['order.total', 'user.name']]]),
        registeredVariables: $registry,
    );
    $configuration = new FlowConfiguration(
        schema: [['uuid' => 'e', 'key' => 'email', 'label' => 'Email', 'type' => 'email']],
        validation: [],
        routing: [],
        emailRoutes: [['event' => 'team_notification', 'enabled' => true, 'template_id' => 4, 'recipient' => 'team@example.com']],
        success: ['type' => 'inline', 'message' => 'Thanks.'],
        placementSnapshot: [],
    );
    $refused = '';
    try {
        (new ReflectionMethod($validator, 'validateEmails'))->invoke($validator, $configuration);
    } catch (DomainException $error) {
        $refused = $error->getMessage();
    }

    // Refused for the variable nothing supplies, and only for it.
    expect($refused)->toContain('user.name')
        ->and($refused)->not->toContain('order.total');
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
