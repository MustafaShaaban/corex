<?php

/**
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Block\FieldRenderer;
use Corex\Forms\Block\FlowBlockRenderer;
use Corex\Forms\Block\FormBlockRenderer;
use Corex\Forms\Block\ProtectedFormRegistry;
use Corex\Forms\FormRegistry;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowConfiguration;
use Corex\Forms\Flow\FlowRepository;
use Corex\Forms\Flow\FlowVersion;
use Corex\Forms\Schema\SchemaExporter;
use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Submission\FlowSchemaFactory;
use Corex\Forms\Submission\FormChallengeContextFactory;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Support\Config\ConfigInterface;
use Corex\Tests\Fixtures\Forms\InMemoryFlowStore;

function flowBlockRenderer(string $state = Flow::STATE_PUBLISHED): FlowBlockRenderer
{
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_attr__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_attr')->alias(static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES));
    Functions\when('esc_url')->returnArg();
    Functions\when('sanitize_key')->alias(static fn (string $value): string => strtolower($value));
    Functions\when('absint')->alias(static fn (mixed $value): int => abs((int) $value));
    Functions\when('wp_create_nonce')->justReturn('flow-nonce');
    Functions\when('wp_enqueue_script')->justReturn(null);
    Functions\when('wp_enqueue_style')->justReturn(null);
    Functions\when('rest_url')->alias(static fn (string $path): string => 'https://example.test/wp-json/' . $path);
    Functions\when('wp_json_encode')->alias(static fn (mixed $data): string => (string) json_encode($data));

    $repository = new FlowRepository(new InMemoryFlowStore());
    $now = new DateTimeImmutable('2026-07-04T10:00:00+00:00');
    $flow = $repository->save(new Flow(
        id: 0,
        uuid: 'd32ef84a-a05d-4c09-a0be-3aff5c0f18dd',
        slug: 'newsletter',
        name: 'Newsletter signup',
        description: '',
        state: Flow::STATE_DRAFT,
        ownerId: 7,
        placementType: Flow::PLACEMENT_BLOCK,
        placementId: null,
        currentDraftVersion: 1,
        publishedVersion: 0,
        testMode: false,
        createdBy: 7,
        updatedBy: 7,
        createdAt: $now,
        updatedAt: $now,
    ));
    $repository->appendVersion(new FlowVersion(
        id: 0,
        flowId: $flow->id,
        versionNumber: 1,
        configuration: new FlowConfiguration(
            schema: [[
                'uuid' => 'field-email',
                'key' => 'email',
                'type' => 'email',
                'label' => 'Email address',
                'placeholder' => 'name@example.test',
                'help_text' => 'We only send product updates.',
                'required' => true,
            ]],
            validation: ['email' => ['required', 'email']],
            routing: ['fallback' => ['type' => 'flow_owner']],
            emailRoutes: [],
            success: ['type' => 'inline', 'message' => 'You are subscribed.'],
            placementSnapshot: [],
        ),
        createdBy: 7,
        createdAt: $now,
    ));
    if ($state === Flow::STATE_PUBLISHED) {
        $repository->save($flow->withState(Flow::STATE_PUBLISHED, 1, 7, $now));
    }

    $resolver = new SchemaResolver(new RuleRegistry());

    $config = new class implements ConfigInterface {
        public function get(string $key, mixed $default = null): mixed
        {
            return $default; // no captcha configured — forms render unprotected
        }

        public function has(string $key): bool
        {
            return false;
        }
    };

    return new FlowBlockRenderer(
        $repository,
        new FlowSchemaFactory($resolver),
        new SchemaExporter(),
        new FieldRenderer(),
        new FormChallengeContextFactory($config),
        new ProtectedFormRegistry(),
    );
}

it('renders a published persisted flow with its immutable schema and visitor endpoint', function () {
    $html = flowBlockRenderer()->render(['flowSlug' => 'newsletter', 'variant' => 'flow'], '', (object) []);

    expect($html)
        ->toContain('data-corex-flow="1"')
        ->toContain('data-corex-flow-version="1"')
        ->toContain('/corex/v1/flows/1/submit')
        ->toContain('name="email"')
        ->toContain('placeholder="name@example.test"')
        ->toContain('We only send product updates.')
        ->toContain('data-corex-success="You are subscribed."')
        ->toContain('data-corex-nonce="flow-nonce"');
});

it('refuses to render a draft or unknown flow', function () {
    expect(flowBlockRenderer(Flow::STATE_DRAFT)->render(['flowSlug' => 'newsletter'], '', (object) []))->toBe('')
        ->and(flowBlockRenderer()->render(['flowSlug' => 'missing'], '', (object) []))->toBe('');
});

it('renders every approved presentation against the same persisted published flow', function (string $variant) {
    $html = flowBlockRenderer()->render(
        ['flowSlug' => 'newsletter', 'variant' => $variant, 'heading' => 'Join the list'],
        '',
        (object) [],
    );

    expect($html)
        ->toContain('corex-flow--' . $variant)
        ->toContain('Newsletter signup');
})->with(['flow', 'form', 'subscribe', 'survey', 'cta-flow']);

it('renders the published inline success message without exposing the form', function () {
    $html = flowBlockRenderer()->render(
        ['flowSlug' => 'newsletter', 'variant' => 'success-message'],
        '',
        (object) [],
    );

    expect($html)
        ->toContain('corex-flow--success-message')
        ->toContain('You are subscribed.')
        ->not->toContain('<form');
});

it('keeps the Form block compatible while defaulting it to persisted flow rendering', function () {
    $resolver = new SchemaResolver(new RuleRegistry());
    $renderer = new FormBlockRenderer(
        new FormRegistry(),
        $resolver,
        new SchemaExporter(),
        new FieldRenderer(),
        flowBlockRenderer(),
    );

    expect($renderer->render(['flowSlug' => 'newsletter'], '', (object) []))
        ->toContain('corex-flow--form')
        ->toContain('/corex/v1/flows/1/submit');
});
