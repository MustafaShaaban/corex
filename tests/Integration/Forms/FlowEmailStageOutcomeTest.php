<?php

/**
 * What a flow's email stage records and sends on a site as it is set up (spec 120, story 4).
 *
 * SC-005 and FR-023: a site that is not set up to deliver (production, no provider or live
 * delivery off: how a new site starts) records the new flow's two emails as not configured, as a
 * flow with no email was before, not as a failed email stage. DECISIONS #455.4: only a team email
 * bound to a template that has nothing to send gives way to the PHP contact notification; one
 * routed by its trigger with no matching route sends nothing, as before, so no upgraded site gains
 * an email.
 *
 * Nothing here is delivered: the site refuses, or keeps every email in Email Studio.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Studio\EmailLayoutRepository;
use Corex\Email\Studio\EmailTemplate;
use Corex\Email\Studio\EmailTemplateRepository;
use Corex\Email\Studio\EmailTemplateVersion;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowConfiguration;
use Corex\Forms\Flow\FlowVersion;
use Corex\Forms\Submission\Stages\EmailStage;
use Corex\Forms\Submission\SubmissionPipelineContext;
use Corex\Mail\Capture\WpMailInterceptor;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\MailDelivery;

beforeEach(function () {
    if (! class_exists(EmailTemplateRepository::class)) {
        $this->markTestSkipped('The email add-on is not active on this install.');
    }
    $this->container = Corex\Boot::app()->container();
    $this->container->make(WpMailInterceptor::class)->register();
    $this->posts = CreatedPosts::watch('corex_email_asset', 'corex_email_log');
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
    $this->settings = [];
});

afterEach(function () {
    MailDelivery::stop($this->settings);
    $this->posts->delete();
    wp_set_current_user(0);
});

/** A template of this test's own, active unless asked for a draft. */
function stageTemplate(object $test, bool $active = true): EmailTemplate
{
    $templates = $test->container->make(EmailTemplateRepository::class);
    $layout = $test->container->make(EmailLayoutRepository::class)->find('transactional');
    $now = new DateTimeImmutable('now');
    $template = $templates->create('stage-' . strtolower(wp_generate_password(8, false, false)), 'Stage probe', get_current_user_id(), $now);
    $version = $templates->saveDraft(new EmailTemplateVersion(
        id: 1,
        templateId: $template->id,
        versionNumber: 1,
        subject: 'Stage probe {{ form.title }}',
        fromName: '',
        fromAddress: '',
        htmlBody: '<p>Thanks.</p>',
        plainText: '',
        plainTextMode: 'auto',
        layoutId: $layout->id,
        layoutVersion: $layout->version,
        variableKeys: [],
        createdBy: get_current_user_id(),
        createdAt: $now,
        checksum: hash('sha256', 'stage-probe'),
    ));

    return $active ? $templates->activate($template->id, $version->versionNumber, get_current_user_id(), $now) : $template;
}

/** @param list<array<string,mixed>> $bindings */
function stageRun(object $test, array $bindings): Corex\Forms\Submission\SubmissionStageResult
{
    $now = new DateTimeImmutable('now');
    $flow = new Flow(
        id: 1,
        uuid: '3f0c1a52-6b1e-4d2a-9c57-0e6a5f1b7d11',
        slug: 'stage-' . strtolower(wp_generate_password(6, false, false)),
        name: 'Stage form',
        description: '',
        state: Flow::STATE_PUBLISHED,
        ownerId: get_current_user_id(),
        placementType: Flow::PLACEMENT_NONE,
        placementId: null,
        currentDraftVersion: 1,
        publishedVersion: 1,
        testMode: false,
        createdBy: get_current_user_id(),
        updatedBy: get_current_user_id(),
        createdAt: $now,
        updatedAt: $now,
    );
    $version = new FlowVersion(1, 1, 1, new FlowConfiguration(
        schema: [['uuid' => 'e', 'key' => 'email', 'label' => 'Your email', 'type' => 'email', 'required' => true]],
        validation: [],
        routing: [],
        emailRoutes: $bindings,
        success: [],
        placementSnapshot: [],
    ), 1, $now);

    // Not stored: there is no submission, so nothing is written to one.
    return $test->container->make(EmailStage::class)->execute(
        new SubmissionPipelineContext($flow, $version, ['email' => 'salma@example.com'], false),
    );
}

/** @return list<array<string,mixed>> The two emails a new flow is created with, bound to these templates. */
function stageDefaults(EmailTemplate $team, EmailTemplate $submitter): array
{
    return [
        ['event' => 'team_notification', 'enabled' => true, 'template_id' => $team->id, 'recipient' => 'team@example.com', 'reply_to' => 'email'],
        ['event' => 'submitter_confirmation', 'enabled' => true, 'template_id' => $submitter->id, 'recipient' => 'email', 'reply_to' => ''],
    ];
}

it('SC-005: records a new flow\'s two emails as not configured, not as a failed stage, on a site not set up to send', function (bool $withProvider) {
    $this->settings = MailDelivery::answerAs(MailDelivery::REFUSE);
    if (! $withProvider) {
        // The way a new site starts: production, and no mail provider chosen.
        $noProvider = static fn (): string => '';
        add_filter('pre_option_corex_mail_provider', $noProvider, 20);
        $this->settings[] = ['pre_option_corex_mail_provider', $noProvider, 20];
    }

    $result = stageRun($this, stageDefaults(stageTemplate($this), stageTemplate($this)));
    $email = $result->context->metadata['email'];

    expect($result->failed())->toBeFalse()
        ->and($email['state'])->toBe('not_configured')
        ->and($email['bindings'])->toHaveKeys(['team_notification', 'submitter_confirmation'])
        ->and($email['bindings'])->not->toHaveKey('admin_failure')
        ->and($result->context->metadata['notification_delivery']['status'])->toBe('not_attempted')
        ->and($result->context->metadata['notification_delivery']['reason_code'])->toBe('delivery_not_set_up');
})->with([
    'a provider, live delivery off' => [true],
    'no provider' => [false],
]);

/** The newest email Email Studio keeps, by its id, or '' when it keeps none. */
function stageNewestCapture(object $test): string
{
    return $test->container->make(CapturedEmailRepository::class)->latest(1)[0]->captureId ?? '';
}

it('DECISIONS #455.4: sends nothing for a team email routed by its trigger when no route matches', function () {
    $this->settings = MailDelivery::answerAs(MailDelivery::CAPTURE);
    $newest = stageNewestCapture($this);

    $result = stageRun($this, [['event' => 'team_notification', 'enabled' => true, 'template_id' => 0, 'recipient' => 'team@example.com']]);

    expect($result->context->metadata['email']['bindings']['team_notification']['state'])->toBe('unrouted')
        ->and(stageNewestCapture($this))->toBe($newest);
});

it('FR-024: sends the PHP contact notification for a team email whose template has no active version', function () {
    $this->settings = MailDelivery::answerAs(MailDelivery::CAPTURE);

    $result = stageRun($this, [['event' => 'team_notification', 'enabled' => true, 'template_id' => stageTemplate($this, false)->id, 'recipient' => 'team@example.com']]);
    $kept = $this->container->make(CapturedEmailRepository::class)->latest(1)[0] ?? null;

    expect($result->context->metadata['email']['bindings']['team_notification']['state'])->toBe('captured')
        ->and($kept?->to)->toBe(['team@example.com']);
});
