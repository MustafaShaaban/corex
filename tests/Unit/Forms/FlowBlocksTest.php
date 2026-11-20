<?php

/**
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Security\ChallengeSettings;
use Brain\Monkey\Functions;
use Corex\Forms\Block\FieldRenderer;
use Corex\Forms\Block\FlowBlockRenderer;
use Corex\Forms\Block\FormBlockRenderer;
use Corex\Forms\Block\ProtectedFormRegistry;
use Corex\Forms\Block\SiteModeNotice;
use Corex\Forms\Block\NoticePanel;
use Corex\Http\VisitorMessage;
use Corex\Http\VisitorMessages;
use Corex\Container\Container;
use Corex\Environment\SiteEnvironment;
use Corex\Http\PublicSubmissionPause;
use Corex\Mail\Capture\MailGate;
use Corex\Mail\Capture\MailHold;
use Corex\Mail\Capture\MailCaptureStore;
use Corex\Mail\Capture\CapturedMessage;
use Corex\Support\Config\Source;
use Corex\Forms\Flow\FlowAvailability;
use Corex\Forms\FormRegistry;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowConfiguration;
use Corex\Forms\Flow\FlowNoticeText;
use Corex\Forms\Flow\FlowRepository;
use Corex\Forms\Flow\FlowResponseCounter;
use Corex\Forms\Flow\FlowStatusResolver;
use Corex\Forms\Flow\FlowVersion;
use Corex\Forms\Submission\PageReference;
use Corex\Forms\Schema\SchemaExporter;
use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Submission\FlowSchemaFactory;
use Corex\Forms\Submission\FormChallengeContextFactory;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Http\RemoteAddress;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\BootLogger;
use Corex\Tests\Fixtures\Forms\InMemoryFlowStore;

/** A container that has somewhere to keep mail (CoreX Mail is active), or nowhere. */
function mailContainer(bool $store): Container
{
    $container = new Container();
    if ($store) {
        $container->instance(MailCaptureStore::class, new class implements MailCaptureStore {
            public function keep(CapturedMessage $message): string
            {
                return 'kept';
            }
        });
    }

    return $container;
}

/**
 * @param array<string,mixed> $settings      the site's config: captcha, `mail.provider`, `mail.live_delivery`,
 *                                           and `app.env` for an environment locked by `.env`
 * @param string              $mode          the stored operations mode
 * @param bool                $administrator whether the visitor is a signed-in administrator
 */
function flowBlockRenderer(
    string $state = Flow::STATE_PUBLISHED,
    array $settings = [],
    string $mode = 'production',
    bool $administrator = false,
    ?FlowAvailability $availability = null,
    ?VisitorMessage $paused = null,
    bool $mailStore = true,
): FlowBlockRenderer {
    Functions\when('get_option')->alias(static fn (string $name, mixed $default = false): mixed => match ($name) {
        SiteEnvironment::MODE_OPTION => $mode,
        'date_format' => 'j F Y',
        'time_format' => 'H:i',
        default => $default,
    });
    Functions\when('wp_get_environment_type')->justReturn('production');
    Functions\when('wp_date')->alias(static fn (string $format, int $timestamp): string => gmdate($format, $timestamp));
    Functions\when('apply_filters')->returnArg(2);
    Functions\when('_x')->returnArg();
    Functions\when('current_user_can')->justReturn($administrator);
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_attr__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_attr')->alias(static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES));
    Functions\when('esc_url')->returnArg();
    Functions\when('sanitize_html_class')->returnArg();
    Functions\when('sanitize_key')->alias(static fn (string $value): string => strtolower($value));
    Functions\when('absint')->alias(static fn (mixed $value): int => abs((int) $value));
    Functions\when('wp_create_nonce')->justReturn('flow-nonce');
    Functions\when('wp_enqueue_script')->justReturn(null);
    Functions\when('wp_enqueue_style')->justReturn(null);
    Functions\when('rest_url')->alias(static fn (string $path): string => 'https://example.test/wp-json/' . $path);
    Functions\when('wp_json_encode')->alias(static fn (mixed $data): string => (string) json_encode($data));
    // A visitor, unless an administrator is asked for: no editor's note, and no page to sign.
    Functions\when('is_user_logged_in')->justReturn($administrator);
    Functions\when('is_singular')->justReturn(false);
    Functions\when('get_queried_object_id')->justReturn(0);

    $repository = new FlowRepository(new InMemoryFlowStore(), new BootLogger(debug: false));
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
    if ($state !== Flow::STATE_DRAFT) {
        $published = $repository->save($flow->withState(Flow::STATE_PUBLISHED, 1, 7, $now));
        if ($availability !== null) {
            $published = $repository->save($published->withAvailability($availability, 7, $now));
        }
        if ($state === Flow::STATE_CLOSED) {
            $repository->save($published->withState(Flow::STATE_CLOSED, 1, 7, $now));
        }
    }

    $resolver = new SchemaResolver(new RuleRegistry());

    // With no settings, no captcha is configured and forms render unprotected.
    $config = new class($settings) implements ConfigInterface, Source {
        /** @param array<string,mixed> $settings */
        public function __construct(private array $settings)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->settings[$key] ?? $default;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->settings);
        }
    };

    $environment = new SiteEnvironment(array_key_exists('app.env', $settings) ? [[SiteEnvironment::BY_DOTENV, $config]] : []);

    return new FlowBlockRenderer(
        $repository,
        new FlowSchemaFactory($resolver),
        new SchemaExporter(),
        new FieldRenderer(),
        new FormChallengeContextFactory(new ChallengeSettings($config), new RemoteAddress()),
        new ProtectedFormRegistry(),
        new FlowStatusResolver(new class implements FlowResponseCounter {
            public function responses(int $flowId): int
            {
                return 0;
            }
        }),
        new FlowNoticeText(),
        new PageReference(),
        new SiteModeNotice(
            $environment,
            new PublicSubmissionPause(new VisitorMessages($paused === null ? [] : [PublicSubmissionPause::FORMS_PAUSED => $paused])),
            new MailGate($environment, $config, new MailHold(), mailContainer($mailStore)),
        ),
        new NoticePanel(),
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

// Spec 104, US4 (#264): Turnstile and hCaptcha could be chosen and nothing placed their widget.
it('gives a protected flow a place for the widget of a provider that shows one', function (string $driver, bool $shown) {
    $html = flowBlockRenderer(settings: [
        'captcha.driver'   => $driver,
        'captcha.secret'   => 'a-secret',
        'captcha.site_key' => 'a-site-key',
    ])->render(['flowSlug' => 'newsletter', 'variant' => 'flow'], '', (object) []);

    $place = '<div class="corex-form__challenge" data-corex-challenge="' . $driver . '" data-corex-sitekey="a-site-key"></div>';

    expect($html)->toContain('name="captcha_token"')
        ->and(str_contains($html, $place . '<button type="submit"'))->toBe($shown);
})->with([
    'turnstile' => ['turnstile', true],
    'hcaptcha'  => ['hcaptcha', true],
    'recaptcha, which shows nothing' => ['recaptcha', false],
]);

/*
 * Spec 124, FR-021 (User Story 10; amended by DECISIONS #521 and #523): a test site says so above
 * every flow's form, a site whose forms are paused says that instead of the form, and neither is
 * said in any other mode or to an administrator of a paused site.
 */

const TEST_SITE_KEPT = 'This is a test copy of the site. What you send here is kept for testing.'
    . " This form's emails stay on the site and are not delivered.";
const TEST_SITE_DELIVERED = 'This is a test copy of the site. What you send here is kept for testing.'
    . " This form's emails are delivered.";
const TEST_SITE_REFUSED = 'This is a test copy of the site. What you send here is kept for testing.'
    . " This form's emails are not delivered.";
const PAUSED_DEFAULT = 'Forms are paused for a short while. Please try again later.';

/** The test-site notice as the page carries it. */
function testSiteNotice(string $message): string
{
    return '<div class="corex-flow-notice corex-flow-notice--test"><p class="corex-flow-notice__title">Test site</p>'
        . '<p class="corex-flow-notice__message">' . $message . '</p></div>';
}

it('FR-021: tells every visitor of a development or staging site, above the form, that it is a test copy whose emails stay on the site', function (string $mode, string $variant) {
    $html = flowBlockRenderer(mode: $mode)->render(
        ['flowSlug' => 'newsletter', 'variant' => $variant, 'intro' => 'Hear from us.'],
        '',
        (object) [],
    );

    $notice = strpos($html, testSiteNotice(TEST_SITE_KEPT));
    expect($notice)->not->toBeFalse()
        ->and(strpos($html, '<h2 class="corex-flow__title">'))->toBeLessThan($notice)
        ->and(strpos($html, '<p class="corex-flow__intro">'))->toBeLessThan($notice)
        ->and(strpos($html, '<form'))->toBeGreaterThan($notice)
        ->and(substr_count($html, '<div class="corex-flow-notice'))->toBe(1);
})->with(['development', 'staging'])->with(['flow', 'form', 'subscribe', 'survey', 'cta-flow']);

it('FR-021: shows the test-site notice to an administrator too', function () {
    expect(flowBlockRenderer(mode: 'staging', administrator: true)->render(['flowSlug' => 'newsletter'], '', (object) []))
        ->toContain(testSiteNotice(TEST_SITE_KEPT))
        ->toContain('<form');
});

it('FR-021 (#523): says the form emails are delivered only where the mail gate delivers them', function (string $mode, array $mail, string $message) {
    expect(flowBlockRenderer(settings: $mail, mode: $mode)->render(['flowSlug' => 'newsletter'], '', (object) []))
        ->toContain(testSiteNotice($message));
})->with([
    'staging with a provider and live delivery' => ['staging', ['mail.provider' => 'smtp', 'mail.live_delivery' => true], TEST_SITE_DELIVERED],
    'staging with live delivery and no provider' => ['staging', ['mail.live_delivery' => true], TEST_SITE_KEPT],
    'staging with a provider and live delivery off' => ['staging', ['mail.provider' => 'smtp', 'mail.live_delivery' => false], TEST_SITE_KEPT],
    'development with a provider and live delivery' => ['development', ['mail.provider' => 'smtp', 'mail.live_delivery' => true], TEST_SITE_KEPT],
]);

it('FR-021 (#650): says the form emails are not delivered where the gate has nowhere to keep them', function (string $mode) {
    expect(flowBlockRenderer(mode: $mode, mailStore: false)->render(['flowSlug' => 'newsletter'], '', (object) []))
        ->toContain(testSiteNotice(TEST_SITE_REFUSED))
        ->not->toContain('stay on the site');
})->with(['development', 'staging']);

it('FR-021 (#650, FR-002): a site locked to Development or Staging says it is a test copy in Coming soon and Maintenance too', function (string $lock, string $mode) {
    expect(flowBlockRenderer(settings: ['app.env' => $lock], mode: $mode)->render(['flowSlug' => 'newsletter'], '', (object) []))
        ->toContain(testSiteNotice(TEST_SITE_KEPT))
        ->toContain('<form');
})->with(['development', 'staging'])->with(['coming-soon', 'maintenance']);

it('FR-021: shows no notice in Production, Coming soon or Maintenance', function (string $mode, bool $administrator) {
    $html = flowBlockRenderer(mode: $mode, administrator: $administrator)->render(['flowSlug' => 'newsletter'], '', (object) []);

    expect($html)->toContain('<form')->not->toContain('corex-flow-notice');
})->with(['production', 'coming-soon', 'maintenance'])->with(['a visitor' => false, 'an administrator' => true]);

it('FR-021: draws the paused panel, and no form, for a visitor while forms are paused', function (string $variant) {
    $html = flowBlockRenderer(mode: 'forms-paused')->render(
        ['flowSlug' => 'newsletter', 'variant' => $variant, 'heading' => 'Join the list'],
        '',
        (object) [],
    );

    expect($html)
        ->toContain('corex-flow--' . $variant)
        ->toContain('<h2 class="corex-flow__title">Join the list</h2><div class="corex-flow-notice">'
            . '<p class="corex-flow-notice__title">Paused</p>'
            . '<p class="corex-flow-notice__message">' . PAUSED_DEFAULT . '</p></div>')
        ->not->toContain('<form')
        ->not->toContain('corex-flow-notice--test')
        ->not->toContain('corex-flow-notice__when');
})->with(['flow', 'form', 'subscribe', 'survey', 'cta-flow']);

it('FR-021: gives an administrator of a paused site the real form and no panel', function () {
    $html = flowBlockRenderer(mode: 'forms-paused', administrator: true)->render(['flowSlug' => 'newsletter'], '', (object) []);

    expect($html)->toContain('<form')->not->toContain('corex-flow-notice');
});

it('FR-021: on a paused site locked to staging, tells a visitor only that forms are paused', function () {
    $renderer = flowBlockRenderer(settings: ['app.env' => 'staging'], mode: 'forms-paused');

    expect($renderer->render(['flowSlug' => 'newsletter'], '', (object) []))
        ->toContain('<p class="corex-flow-notice__title">Paused</p>')
        ->not->toContain('corex-flow-notice--test')
        ->not->toContain('<form');
});

it('FR-021 (#521): a closed or scheduled flow on a paused site shows only its own notice', function (string $state, FlowAvailability $availability, string $title) {
    $html = flowBlockRenderer(state: $state, mode: 'forms-paused', availability: $availability)
        ->render(['flowSlug' => 'newsletter'], '', (object) []);

    expect($html)
        ->toContain('<p class="corex-flow-notice__title">' . $title . '</p>')
        ->not->toContain('Paused')
        ->not->toContain(PAUSED_DEFAULT)
        ->and(substr_count($html, 'class="corex-flow-notice"'))->toBe(1);
})->with([
    'closed' => [Flow::STATE_CLOSED, new FlowAvailability(), 'Closed'],
    'not open yet' => [Flow::STATE_PUBLISHED, new FlowAvailability(opensAt: new DateTimeImmutable('2099-01-01T09:00:00+00:00')), 'Not open yet'],
]);

it('FR-021 (#650): keeps the success message block filled while forms are paused', function () {
    expect(flowBlockRenderer(mode: 'forms-paused')->render(['flowSlug' => 'newsletter', 'variant' => 'success-message'], '', (object) []))
        ->toContain('You are subscribed.')
        ->not->toContain('corex-flow-notice');
});

it('FR-021 (#650): still leaves the success message block empty for a closed flow', function () {
    expect(flowBlockRenderer(state: Flow::STATE_CLOSED, mode: 'forms-paused')->render(['flowSlug' => 'newsletter', 'variant' => 'success-message'], '', (object) []))
        ->toBe('');
});

it('FR-021: says the owner\'s message whole, however long, and when forms are back while that time is ahead', function () {
    $long = substr(str_repeat('Back after the yearly stocktake. ', 10), 0, 299) . '.';
    $html = flowBlockRenderer(mode: 'forms-paused', paused: new VisitorMessage($long, new DateTimeImmutable('+2 days 14:30')))
        ->render(['flowSlug' => 'newsletter'], '', (object) []);
    $backBy = new DateTimeImmutable('+2 days 14:30');

    expect($html)->toContain('<p class="corex-flow-notice__title">Paused</p><p class="corex-flow-notice__message">' . $long . '</p>'
        . '<p class="corex-flow-notice__when">Back by ' . gmdate('j F Y', $backBy->getTimestamp()) . ' at '
        . gmdate('H:i', $backBy->getTimestamp()) . '.</p></div>');
});

it('FR-021: never shows a back-by time that has passed, and says it is taking longer instead', function () {
    $html = flowBlockRenderer(mode: 'forms-paused', paused: new VisitorMessage('', new DateTimeImmutable('-1 hour')))
        ->render(['flowSlug' => 'newsletter'], '', (object) []);

    expect($html)
        ->toContain('<p class="corex-flow-notice__message">' . PAUSED_DEFAULT . '</p>'
            . '<p class="corex-flow-notice__when">This is taking longer than planned.</p></div>')
        ->not->toContain('Back by');
});
