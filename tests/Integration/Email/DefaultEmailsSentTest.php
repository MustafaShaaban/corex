<?php

/**
 * The emails CoreX ships are sent where they are active (spec 120, stories 4 and 6): a new flow's
 * notification and confirmation, the newsletter's double opt-in and welcome, the account's
 * password reset and welcome; and a template that cannot be drawn gives way to the sender's own
 * email, and says so.
 *
 * The site answers as a development site, so every email is kept in Email Studio.
 *
 * @package Corex\Tests\Integration\Email
 */

declare(strict_types=1);

use Corex\Email\Capture\CapturedEmail;
use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Design\EmailDesignFailedEvent;
use Corex\Email\Studio\EmailTemplate;
use Corex\Email\Studio\EmailTemplateRepository;
use Corex\Email\Studio\EmailTemplateVersion;
use Corex\Events\ListenerProvider;
use Corex\Forms\Flow\FlowController;
use Corex\Forms\Flow\WpFlowStore;
use Corex\Forms\Submission\FlowSubmissionController;
use Corex\Mail\Capture\WpMailInterceptor;
use Corex\Mail\DesignedEmails;
use Corex\Newsletter\Subscriber\SubscriberRepository;
use Corex\Newsletter\Subscriber\SubscriberStore;
use Corex\Newsletter\Subscription\SubscriptionService;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\MailDelivery;
use Corex\Tests\Support\WatchedTransients;

beforeEach(function () {
    $this->container = Corex\Boot::app()->container();
    $this->container->make(WpMailInterceptor::class)->register();
    $this->posts = CreatedPosts::watch('corex_email_asset', 'corex_email_log', WpFlowStore::POST_TYPE, 'corex_submission');
    $this->transients = WatchedTransients::watch('corex_throttle_');
    $this->settings = MailDelivery::answerAs(MailDelivery::CAPTURE);
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
});

afterEach(function () {
    MailDelivery::stop($this->settings);
    $this->posts->delete();
    $this->transients->restore();
    wp_set_current_user(0);
});

/** The default template with this slug, when this site has it active; the test is skipped otherwise. */
function activeDefault(object $test, string $slug): EmailTemplate
{
    $template = $test->container->make(EmailTemplateRepository::class)->findBySlug($slug);
    if ($template === null || $template->status !== EmailTemplate::STATUS_ACTIVE) {
        test()->markTestSkipped("The default template {$slug} is not active on this site.");
    }

    return $template;
}

/** @return array<string,CapturedEmail> The emails kept since the test began, by subject. */
function keptBySubject(object $test, int $limit = 10): array
{
    $kept = [];
    foreach ($test->container->make(CapturedEmailRepository::class)->latest($limit) as $capture) {
        $kept[$capture->subject] ??= $capture;
    }

    return $kept;
}

function defaultsRequest(string $method, string $route, array $payload = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_body_params($payload);

    return $request;
}

it('starts a new flow with the two emails CoreX ships, and sends both on a submission', function () {
    activeDefault($this, 'admin-notification');
    activeDefault($this, 'contact-confirmation');
    $flows = $this->container->make(FlowController::class);
    $tag = strtolower(wp_generate_password(6, false, false));

    $created = $flows->create(defaultsRequest('POST', '/corex/v1/flows', [
        'slug' => 'quote-' . $tag,
        'name' => 'Quote request',
        'owner_id' => get_current_user_id(),
        'placement_type' => 'block',
        'configuration' => [
            'schema' => [
                ['uuid' => 'n', 'key' => 'name', 'label' => 'Your name', 'type' => 'text', 'required' => true],
                ['uuid' => 'e', 'key' => 'email', 'label' => 'Your email', 'type' => 'email', 'required' => true],
            ],
            'validation' => ['email' => ['required', 'email']],
            'routing' => ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]],
            'email_routes' => [],
            'success' => ['type' => 'inline', 'message' => 'Received.'],
            'placement_snapshot' => ['type' => 'block'],
        ],
    ]))->get_data()['data'];
    $flowId = $created['flow']['id'];
    $events = array_column($created['version']['configuration']['email_routes'], 'event');

    $publish = defaultsRequest('POST', '/corex/v1/flows/' . $flowId . '/publish', ['expected_version' => 1]);
    $publish->set_url_params(['id' => $flowId]);
    $flows->publish($publish);
    $submit = defaultsRequest('POST', '/corex/v1/flows/' . $flowId . '/submit', ['name' => 'Salma & Sons', 'email' => 'salma@example.com']);
    $submit->set_url_params(['id' => $flowId]);
    $this->container->make(FlowSubmissionController::class)->submit($submit);
    $kept = keptBySubject($this);

    expect($events)->toBe(['team_notification', 'submitter_confirmation'])
        ->and($kept)->toHaveKeys(['New submission: Quote request', 'Thanks, we have your message'])
        ->and($kept['New submission: Quote request']->to)->toBe([(string) get_option('admin_email')])
        ->and($kept['New submission: Quote request']->body)->toContain('Your name')
        ->and($kept['New submission: Quote request']->body)->toContain('Salma &amp; Sons')
        ->and($kept['New submission: Quote request']->body)->toContain('Open the submission')
        ->and($kept['New submission: Quote request']->body)->toContain('this address receives notifications from')
        ->and($kept['Thanks, we have your message']->to)->toBe(['salma@example.com'])
        ->and($kept['Thanks, we have your message']->plainText)->toContain('Your email: salma@example.com');
});

it('sends the double opt-in and, on confirmation, the welcome, with an unsubscribe link', function () {
    activeDefault($this, 'double-opt-in');
    activeDefault($this, 'newsletter-welcome');
    $service = $this->container->make(SubscriptionService::class);
    $email = 'nl-' . strtolower(wp_generate_password(6, false, false)) . '@example.com';

    $service->subscribe($email, ['news'], consent: true);
    $optIn = keptBySubject($this)['Confirm your subscription'] ?? null;
    preg_match('~corex_newsletter=confirm&token=([^"&<\s]+)~', html_entity_decode((string) $optIn?->body), $token);
    $confirmed = $service->confirm(rawurldecode($token[1] ?? ''));
    $welcome = keptBySubject($this)['Welcome to ' . html_entity_decode((string) get_bloginfo('name'), ENT_QUOTES)] ?? null;
    $subscriber = $this->container->make(SubscriberStore::class)->findByEmail($email);
    $this->container->make(SubscriberRepository::class)->delete((int) $subscriber['id']);

    expect($optIn?->to)->toBe([$email])
        ->and($optIn?->plainText)->toContain('Button not working? Paste this link into your browser:')
        ->and(html_entity_decode((string) $optIn?->body))->toContain('corex_newsletter=unsubscribe')
        ->and($confirmed)->toBeTrue()
        ->and($welcome?->to)->toBe([$email]);
});

it('sends the newsletter\'s own confirmation, in the frame with its unsubscribe link, where no designed one is sent', function () {
    // As on a site whose "Double opt-in" is a draft: the add-on is given no designed emails.
    $service = new SubscriptionService(
        $this->container->make(SubscriberStore::class),
        $this->container->make(Corex\Newsletter\TokenSigner::class),
        $this->container->make(Corex\Mail\Mailer::class),
        null,
    );
    $email = 'nl-' . strtolower(wp_generate_password(6, false, false)) . '@example.com';

    $service->subscribe($email, ['news'], consent: true);
    $kept = keptBySubject($this)['Please confirm your subscription'] ?? null;
    $subscriber = $this->container->make(SubscriberStore::class)->findByEmail($email);
    $this->container->make(SubscriberRepository::class)->delete((int) $subscriber['id']);

    expect($kept?->to)->toBe([$email])
        ->and($kept?->body)->toContain('You receive this email because you subscribed to updates from')
        ->and(html_entity_decode((string) $kept?->body))->toMatch('~corex_newsletter=unsubscribe[^"]*">Unsubscribe</a>~')
        ->and($kept?->plainText)->toContain('Confirm subscription (');
});

it('gives way to the sender\'s own email where a template cannot be drawn, and says so', function () {
    $template = activeDefault($this, 'newsletter-welcome');
    $templates = $this->container->make(EmailTemplateRepository::class);
    $active = $templates->findVersion($template->id, $template->activeVersion);
    $now = new DateTimeImmutable('now');
    // The template's record is the site's: it is put back as it was once the test is over.
    $store = $this->container->make(Corex\Email\Studio\WpEmailStudioStore::class);
    $record = $store->find($template->id);
    $broken = $templates->saveDraft(new EmailTemplateVersion(
        id: 1,
        templateId: $template->id,
        versionNumber: 1,
        subject: $active->subject,
        fromName: '',
        fromAddress: '',
        htmlBody: $active->htmlBody . '{{> spec120-missing }}',
        plainText: '',
        plainTextMode: 'auto',
        layoutId: $active->layoutId,
        layoutVersion: $active->layoutVersion,
        variableKeys: [],
        createdBy: get_current_user_id(),
        createdAt: $now,
        checksum: hash('sha256', 'broken'),
    ));
    $templates->activate($template->id, $broken->versionNumber, get_current_user_id(), $now);
    $heard = [];
    $this->container->make(ListenerProvider::class)->listen(EmailDesignFailedEvent::class, function (object $event) use (&$heard): void {
        $heard[] = $event;
    });

    try {
        $sent = $this->container->make(DesignedEmails::class)->send('newsletter-welcome', ['a@example.com'], [], 'Newsletter: welcome');
    } finally {
        $store->update($template->id, $record['name'], $record['payload']);
    }

    expect($sent)->toBeNull()
        ->and($heard)->toHaveCount(1)
        ->and($heard[0]->reason)->toBe(EmailDesignFailedEvent::TEMPLATE_FAILED)
        ->and($heard[0]->design)->toBe('newsletter-welcome')
        ->and($heard[0]->detail)->toBe('The partial "spec120-missing" is not available.');
});

/** A published page with the account block, and its signed mark, for the account emails. */
function accountPage(object $test): array
{
    $pageId = (int) wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => 'Account ' . strtolower(wp_generate_password(6, false, false)),
        'post_content' => '<!-- wp:corex/account /-->',
    ]);

    return [$pageId, $test->container->make(Corex\Profile\Reset\AccountPageMark::class)->of($pageId)];
}

it('sends a reset asked on the account page in the frame, linking to that page and never to the login screen', function () {
    activeDefault($this, 'password-reset');
    [$pageId, $mark] = accountPage($this);
    $page = (string) get_permalink($pageId);
    $login = 'reset-' . strtolower(wp_generate_password(6, false, false));
    $userId = (int) wp_insert_user(['user_login' => $login, 'user_email' => $login . '@example.com', 'user_pass' => 'a-first-passphrase', 'display_name' => 'Salma']);
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $request = new WP_REST_Request('POST', '/corex/v1/account/reset-request');
    $request->set_body_params(['login' => $login, 'page' => $mark]);

    try {
        rest_do_request($request);
        $kept = $this->container->make(CapturedEmailRepository::class)->latest(1)[0];
    } finally {
        unset($_SERVER['REMOTE_ADDR']);
        wp_delete_post($pageId, true);
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($userId);
    }
    $html = html_entity_decode($kept->body, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    expect($kept->subject)->toStartWith('Reset your password for ')
        ->and($kept->to)->toBe([$login . '@example.com'])
        ->and($html)->toContain('href="' . $page)
        ->and($html)->toContain('corex_reset_key=')
        ->and($html)->not->toContain('wp-login.php')
        ->and($html)->toContain('The link works for 1 day.')
        ->and($html)->toContain('You receive this email because of an account on')
        ->and($kept->plainText)->toContain('Did not ask for this? Ignore this email and your password stays the same.');
});

it('welcomes a new member in the frame, linking to the account page, with no link to set a password', function () {
    activeDefault($this, 'account-welcome');
    [$pageId, $mark] = accountPage($this);
    $page = (string) get_permalink($pageId);
    $registration = get_option('users_can_register');
    update_option('users_can_register', '1');
    $email = 'welcome-' . strtolower(wp_generate_password(6, false, false)) . '@example.com';
    $request = new WP_REST_Request('POST', '/corex/v1/account/register');
    $request->set_body_params([
        'email' => $email,
        'password' => 'a-long-enough-passphrase-1',
        'password_confirm' => 'a-long-enough-passphrase-1',
        'consent' => '1',
        'page' => $mark,
    ]);

    try {
        rest_do_request($request);
        $kept = $this->container->make(CapturedEmailRepository::class)->latest(5);
    } finally {
        update_option('users_can_register', $registration);
        wp_delete_post($pageId, true);
        $userId = email_exists($email);
        if ($userId !== false) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user((int) $userId);
        }
    }
    $toTheMember = array_values(array_filter($kept, static fn ($capture): bool => $capture->to === [$email]));

    expect($toTheMember)->toHaveCount(1)
        ->and($toTheMember[0]->subject)->toStartWith('Welcome to ')
        ->and(html_entity_decode($toTheMember[0]->body))->toContain('href="' . $page . '"')
        ->and($toTheMember[0]->body)->not->toContain('wp-login.php')
        ->and($toTheMember[0]->body)->not->toContain('action=rp')
        ->and($toTheMember[0]->plainText)->toContain('Go to your account (');
});
