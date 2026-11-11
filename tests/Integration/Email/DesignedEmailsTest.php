<?php

/**
 * Spec 120 against WordPress: the preview and the test send are the email that is sent (story 1),
 * a template's sender and plain text are sent (story 2), the frame is on every CoreX email
 * (story 3), the defaults install and reset (story 4), a missing value is left out and named
 * (story 5), and a finished message may carry its own plain text (story 7).
 *
 * Every email here is kept on the site: the site answers as a development site, or as production
 * with the transport stopped before it is reached.
 *
 * @package Corex\Tests\Integration\Email
 */

declare(strict_types=1);

use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Delivery\EmailAttemptRepository;
use Corex\Email\MailServiceProvider;
use Corex\Email\Routing\EmailRouteService;
use Corex\Email\Studio\DefaultEmailTemplates;
use Corex\Email\Studio\EmailLayoutRepository;
use Corex\Email\Studio\EmailStudioController;
use Corex\Email\Studio\EmailTemplate;
use Corex\Email\Studio\EmailTemplateRepository;
use Corex\Email\Studio\EmailTemplateVersion;
use Corex\Mail\Capture\WpMailInterceptor;
use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\MailDelivery;
use Corex\Tests\Support\OptionRow;

beforeEach(function () {
    $this->container = Corex\Boot::app()->container();
    $this->container->make(WpMailInterceptor::class)->register();
    $this->assets = CreatedPosts::watch('corex_email_asset', 'corex_email_log');
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
    $this->settings = MailDelivery::answerAs(MailDelivery::CAPTURE);
});

afterEach(function () {
    MailDelivery::stop($this->settings);
    $this->assets->delete();
});

/** An active template of this test's own, arranged by the shipped transactional layout. */
function designedProbe(object $test, string $html, string $from = '', string $fromName = '', string $plain = '', string $mode = 'auto'): EmailTemplate
{
    $templates = $test->container->make(EmailTemplateRepository::class);
    $layout = $test->container->make(EmailLayoutRepository::class)->find('transactional');
    $now = new DateTimeImmutable('now');
    $template = $templates->create('spec120-' . strtolower(wp_generate_password(8, false, false)), 'Spec 120 probe', get_current_user_id(), $now);
    $version = $templates->saveDraft(new EmailTemplateVersion(
        id: 1,
        templateId: $template->id,
        versionNumber: 1,
        subject: 'Hello {{ form.title }}',
        fromName: $fromName,
        fromAddress: $from,
        htmlBody: $html,
        plainText: $plain,
        plainTextMode: $mode,
        layoutId: $layout->id,
        layoutVersion: $layout->version,
        variableKeys: [],
        createdBy: get_current_user_id(),
        createdAt: $now,
        checksum: hash('sha256', $html . $plain . $from),
    ));

    return $templates->activate($template->id, $version->versionNumber, get_current_user_id(), $now);
}

/** A request with the draft fields of a template's active version, as the editor posts them. */
function studioDraftRequest(object $test, EmailTemplate $template, array $more = []): WP_REST_Request
{
    $version = $test->container->make(EmailTemplateRepository::class)->findVersion($template->id, $template->activeVersion);
    $request = new WP_REST_Request('POST', '/corex/v1/email-studio/templates/' . $template->id . '/preview');
    $request->set_url_params(['id' => (string) $template->id]);
    foreach ([
        'subject' => $version->subject,
        'from_name' => $version->fromName,
        'from_address' => $version->fromAddress,
        'html_body' => $version->htmlBody,
        'plain_text' => $version->plainText,
        'plain_text_mode' => $version->plainTextMode,
        'layout_id' => $version->layoutId,
        'layout_version' => $version->layoutVersion,
        ...$more,
    ] as $key => $value) {
        $request->set_param($key, $value);
    }

    return $request;
}

it('previews the draft framed by the server, with samples that can be changed and a right-to-left view', function () {
    $template = designedProbe($this, '<p>Hello {{ submission.name }} about {{ form.title }}.</p>');
    $controller = $this->container->make(EmailStudioController::class);

    $drawn = $controller->previewTemplate(studioDraftRequest($this, $template))->get_data()['data'];
    $changed = $controller->previewTemplate(studioDraftRequest($this, $template, [
        'samples' => ['form.title' => 'Quote request'],
        'direction' => 'rtl',
    ]))->get_data()['data'];

    expect($drawn['subject'])->toBe('Hello Contact form')
        ->and($drawn['html'])->toContain('Hello Jane Cooper about Contact form.')
        ->and($drawn['html'])->toContain('This message was sent by')
        // The draft's own variables, then its layout's (the shipped header and footer name the site).
        ->and(array_column($drawn['samples'], 'value', 'key'))->toBe([
            'form.title' => 'Contact form',
            'submission.name' => 'Jane Cooper',
            'site.name' => wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES),
        ])
        ->and($drawn['text'])->toStartWith('Hello Jane Cooper about Contact form.')
        ->and($changed['subject'])->toBe('Hello Quote request')
        ->and($changed['html'])->toContain(' dir="rtl"');
});

it('FR-002: samples the variables its layout uses too, so a newsletter footer previews with its links', function () {
    $template = designedProbe($this, '<p>Hello {{ submission.name }}.</p>');
    $newsletter = $this->container->make(EmailLayoutRepository::class)->find('newsletter');
    $controller = $this->container->make(EmailStudioController::class);

    $drawn = $controller->previewTemplate(studioDraftRequest($this, $template, [
        'layout_id' => $newsletter->id,
        'layout_version' => $newsletter->version,
    ]))->get_data()['data'];
    $samples = array_column($drawn['samples'], 'value', 'key');

    expect(array_keys($samples))->toContain('submission.name', 'links.unsubscribe', 'links.preferences')
        ->and($samples['links.unsubscribe'])->toContain('corex_sample=1')
        ->and($drawn['html'])->toContain('>Unsubscribe</a>')
        ->and($drawn['html'])->not->toContain('href=""')
        ->and($drawn['text'])->toMatch('/Unsubscribe \(http[^)]+corex_sample=1\)/');
});

it('names the variable or the partial a template cannot be drawn without, and sends no test of it', function () {
    $template = designedProbe($this, '<p>Hello</p>');
    $controller = $this->container->make(EmailStudioController::class);
    $keptBefore = count($this->container->make(CapturedEmailRepository::class)->latest(100));

    $unknown = $controller->previewTemplate(studioDraftRequest($this, $template, ['html_body' => '<p>{{ nonsense.key }}</p>']));
    $partial = $controller->previewTemplate(studioDraftRequest($this, $template, ['html_body' => '<p>Hi</p>{{> promo }}']));
    $test = $controller->testSend(studioDraftRequest($this, $template, [
        'template_id' => $template->id,
        'to' => ['me@example.test'],
        'html_body' => '<p>{{ nonsense.key }}</p>',
    ]));

    expect($unknown->get_status())->toBe(422)
        ->and($unknown->get_data()['details']['fields']['variables'])->toBe('Unknown variable: nonsense.key.')
        ->and($partial->get_data()['details']['fields']['partials'])->toBe('The partial "promo" is not available.')
        ->and($test->get_status())->toBe(422)
        // Nothing was sent, so nothing was kept.
        ->and(count($this->container->make(CapturedEmailRepository::class)->latest(100)))->toBe($keptBefore);
});

it('refuses a test to what is not an address, before anything is drawn or sent', function () {
    $template = designedProbe($this, '<p>Hello</p>');

    $response = $this->container->make(EmailStudioController::class)->testSend(studioDraftRequest($this, $template, [
        'template_id' => $template->id,
        'to' => ['not-an-address'],
    ]));

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['code'])->toBe('email_test_invalid_address')
        ->and($response->get_data()['details']['fields']['to'])->toBe('Enter a valid email address: not-an-address is not one.');
});

it('sends as a test exactly the email it previews, with "[Test]" before its subject, and says what happened', function () {
    $template = designedProbe($this, '<p>Hello {{ submission.name }}.</p><ul><li>One</li></ul><p><a href="https://example.com/x">More</a></p>');
    $controller = $this->container->make(EmailStudioController::class);
    $preview = $controller->previewTemplate(studioDraftRequest($this, $template))->get_data()['data'];

    $sent = $controller->testSend(studioDraftRequest($this, $template, [
        'template_id' => $template->id,
        'to' => ['me@example.test'],
    ]))->get_data()['data'];
    $kept = $this->container->make(CapturedEmailRepository::class)->latest(1)[0];

    expect($sent['summary'])->toBe('Captured: nothing left this site')
        ->and($sent['log_url'])->toContain('tab=logs#corex-attempt-' . $sent['result']['attempt_id'])
        ->and($kept->subject)->toBe('[Test] ' . $preview['subject'])
        ->and($kept->to)->toBe(['me@example.test'])
        ->and($kept->plainText)->toBe($preview['text'])
        ->and($kept->plainText)->toContain("- One\n\nMore (https://example.com/x)")
        // The same document, but for the subject in its title.
        ->and(str_replace('[Test] ', '', $kept->body))->toBe($preview['html']);
});

it('sends a routed template from its own sender with its plain text, and from the site\'s without one', function () {
    MailDelivery::stop($this->settings);
    $this->settings = MailDelivery::answerAs(MailDelivery::DELIVER);
    $sent = [];
    $spy = function (array $atts) use (&$sent): array {
        $sent[] = $atts;

        return $atts;
    };
    add_filter('wp_mail', $spy);
    $own = designedProbe($this, '<p>Price under {{ submission.price }}</p>', 'sales@example.com', 'Sales');
    $plain = designedProbe($this, '<p>Hi</p>');

    try {
        $mailer = $this->container->make(EmailRouteService::class);
        $result = $mailer->dispatchTemplate($own->id, ['sam@example.com'], null, ['submission' => ['price' => 'under <500 EGP']]);
        $mailer->dispatchTemplate($plain->id, ['sam@example.com'], null, []);
    } finally {
        remove_filter('wp_mail', $spy);
    }

    expect($result?->state)->toBe('sent')
        ->and(implode("\n", (array) $sent[0]['headers']))->toContain('From: Sales <sales@example.com>')
        ->and($sent[0]['message'])->toContain('under &lt;500 EGP')
        ->and(implode("\n", (array) $sent[1]['headers']))->not->toContain('sales@example.com');
});

it('keeps an upgraded site\'s templates sending from the site\'s sender until they are saved again, and a new one from its own', function () {
    MailDelivery::stop($this->settings);
    $this->settings = MailDelivery::answerAs(MailDelivery::DELIVER);
    $versionRow = OptionRow::capture(MailServiceProvider::STUDIO_DEFAULTS_OPTION);
    $sent = [];
    $spy = function (array $atts) use (&$sent): array {
        $sent[] = implode("\n", (array) $atts['headers']);

        return $atts;
    };
    // Saved before this release: its From was stored and never sent.
    $before = designedProbe($this, '<p>Hello</p>', 'placeholder@example.com', 'Placeholder');
    // The upgrade marks every revision on the install: each that is the site's, not this test's,
    // is put back as it was once the test is over.
    $store = $this->container->make(Corex\Email\Studio\WpEmailStudioStore::class);
    $siteRevisions = [];
    foreach ($store->all('email_template') as $template) {
        if ($template['id'] !== $before->id) {
            array_push($siteRevisions, ...$store->all('email_template_version', $template['id']));
        }
    }
    update_option(MailServiceProvider::STUDIO_DEFAULTS_OPTION, '3');
    add_filter('wp_mail', $spy);

    try {
        (new MailServiceProvider($this->container))->registerEmailStudio();
        $after = designedProbe($this, '<p>Hello</p>', 'sales@example.com', 'Sales');
        $mailer = $this->container->make(EmailRouteService::class);
        $mailer->dispatchTemplate($before->id, ['sam@example.com'], null, []);
        $mailer->dispatchTemplate($after->id, ['sam@example.com'], null, []);

        // The owner saves the old template again: from now on its From is the one it says.
        $templates = $this->container->make(EmailTemplateRepository::class);
        $stored = $templates->findVersion($before->id, $before->activeVersion);
        $resaved = $templates->saveDraft(new EmailTemplateVersion(...[...get_object_vars($stored), 'sentFromSite' => false]));
        $templates->activate($before->id, $resaved->versionNumber, get_current_user_id(), new DateTimeImmutable('now'));
        $mailer->dispatchTemplate($before->id, ['sam@example.com'], null, []);
    } finally {
        remove_filter('wp_mail', $spy);
        OptionRow::restore(MailServiceProvider::STUDIO_DEFAULTS_OPTION, $versionRow);
        foreach ($siteRevisions as $revision) {
            $store->update($revision['id'], $revision['name'], $revision['payload']);
        }
    }
    $untouched = array_map(static fn (array $revision): mixed => $store->find($revision['id'])['payload']['sender'] ?? null, $siteRevisions);

    expect($stored->sentFromSite)->toBeTrue()
        // The site's own revisions are as they were before the test.
        ->and($untouched)->toBe(array_map(static fn (array $revision): mixed => $revision['payload']['sender'] ?? null, $siteRevisions))
        ->and($sent[0])->not->toContain('placeholder@example.com')
        ->and($sent[0])->not->toContain('Placeholder')
        ->and($sent[1])->toContain('From: Sales <sales@example.com>')
        ->and($sent[2])->toContain('From: Placeholder <placeholder@example.com>');
});

it('leaves out a block whose value is missing, and names the variable on the email\'s log row', function () {
    $template = designedProbe($this, '<p>Thanks for writing.</p><p>Phone: {{ submission.phone }}</p><p>Hello {{ submission.name }}</p>');

    $result = $this->container->make(EmailRouteService::class)->dispatchTemplate(
        $template->id,
        ['sam@example.com'],
        null,
        ['submission' => ['name' => '<b>Sam</b>']],
    );
    $kept = $this->container->make(CapturedEmailRepository::class)->latest(1)[0];
    $attempt = $this->container->make(EmailAttemptRepository::class)->findByAttemptId((string) $result?->attemptId);

    expect($kept->body)->not->toContain('Phone:')
        ->and($kept->body)->not->toContain('{{')
        ->and($kept->body)->toContain('Hello &lt;b&gt;Sam&lt;/b&gt;')
        ->and($attempt?->missingVariables)->toBe(['form.title', 'submission.phone']);
});

it('sends a finished message with the plain text it was given, and one without it as before', function () {
    $mailer = $this->container->make(Mailer::class);

    $mailer->send(new MailRequest(to: ['a@example.test'], subject: 'Spec 120 with text', body: '<p>Hello</p>', plainText: 'Hello, in words'));
    $withText = $this->container->make(CapturedEmailRepository::class)->latest(1)[0];
    $mailer->send(new MailRequest(to: ['a@example.test'], subject: 'Spec 120 without text', body: '<p>Hello</p>', plainText: '  '));
    $without = $this->container->make(CapturedEmailRepository::class)->latest(1)[0];

    expect($withText->plainText)->toBe('Hello, in words')
        // Sent as given: no frame is added to a finished message.
        ->and($withText->body)->toBe('<p>Hello</p>')
        ->and($without->subject)->toBe('Spec 120 without text')
        ->and($without->body)->toBe('<p>Hello</p>');
});

it('puts a default back as it was shipped, through its route, as a new version', function () {
    $templates = $this->container->make(EmailTemplateRepository::class);
    $template = $templates->findBySlug('admin-notification');
    if ($template === null) {
        $this->markTestSkipped('This site has no default templates installed.');
    }
    $store = $this->container->make(Corex\Email\Studio\WpEmailStudioStore::class);
    // The template's own record is the site's: it is put back as it was, and the version this
    // test adds is deleted with the other posts it made.
    $record = $store->find($template->id);
    $before = count($templates->versions($template->id));
    $controller = $this->container->make(EmailStudioController::class);
    $reset = new WP_REST_Request('POST', '/corex/v1/email-studio/templates/' . $template->id . '/reset');
    $reset->set_url_params(['id' => (string) $template->id]);
    $notDefault = new WP_REST_Request('POST', '/corex/v1/email-studio/templates/1/reset');
    $notDefault->set_url_params(['id' => (string) designedProbe($this, '<p>x</p>')->id]);

    try {
        $answer = $controller->resetTemplate($reset);
        $versions = count($templates->versions($template->id));
    } finally {
        $store->update($template->id, $record['name'], $record['payload']);
    }

    expect($answer->get_status())->toBe(200)
        ->and($answer->get_data()['data']['version']['html_body'])
        ->toBe($this->container->make(DefaultEmailTemplates::class)->definition('admin-notification')->htmlBody)
        ->and($versions)->toBe($before + 1)
        ->and($controller->resetTemplate($notDefault)->get_status())->toBe(404);
});

it('FR-028: names on its log row a variable a designed email was sent without', function () {
    $template = designedProbe($this, '<p>Thanks.</p><p>Phone: {{ submission.phone }}</p>');

    $result = $this->container->make(Corex\Mail\DesignedEmails::class)->send($template->slug, ['sam@example.com'], [], 'Spec 120: designed probe');

    expect($result?->logId)->not->toBeNull()
        // The probe's subject names the form, which this email was not given either.
        ->and(get_post_meta((int) $result?->logId, 'corex_mail_missing_variables', true))->toBe('form.title, submission.phone');
});
