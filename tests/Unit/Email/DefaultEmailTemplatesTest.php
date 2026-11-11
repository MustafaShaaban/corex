<?php

/**
 * The Email Studio templates CoreX ships (spec 120, story 4): active on a new site, drafts on an
 * upgraded one, made once, drawn in the frame, and put back as shipped on request.
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Email\Design\EmailKind;
use Corex\Email\Studio\DefaultEmailTemplate;
use Corex\Email\Studio\DefaultEmailTemplates;
use Corex\Email\Studio\EmailLayoutRepository;
use Corex\Email\Studio\EmailTemplate;
use Corex\Email\Studio\EmailTemplateRepository;
use Corex\Email\Studio\EmailTemplateService;
use Corex\Email\Studio\EmailVariableCatalog;
use Corex\Tests\Support\FixedEmailFrame;
use Corex\Tests\Support\InMemoryEmailStudioStore;

beforeEach(function () {
    $this->savedOptions = [];
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->alias(static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES));
    Functions\when('get_option')->alias(fn (string $name, mixed $default = false): mixed => $this->savedOptions[$name] ?? $default);
    Functions\when('update_option')->alias(function (string $name, mixed $value): bool {
        $this->savedOptions[$name] = $value;

        return true;
    });
    Functions\when('wp_set_option_autoload')->justReturn(true);

    $store = new InMemoryEmailStudioStore();
    (new EmailLayoutRepository($store))->installDefaults(false, 1, new DateTimeImmutable('2026-10-23'));
    $this->templates = new EmailTemplateRepository($store);
    $this->editor = new EmailTemplateService(new EmailVariableCatalog(), null, FixedEmailFrame::named('Acme'));
    $this->defaults = new DefaultEmailTemplates($this->templates, new EmailLayoutRepository($store), $this->editor);
});

/** @return array<string,string> Each template's status, by slug. */
function shippedStatuses(EmailTemplateRepository $templates): array
{
    $statuses = [];
    foreach ($templates->all() as $template) {
        $statuses[$template->slug] = $template->status;
    }

    return $statuses;
}

it('starts a new site with the four defaults, active', function () {
    $this->defaults->install(true, 1, new DateTimeImmutable('2026-10-23'));

    expect(shippedStatuses($this->templates))->toBe([
        'contact-confirmation' => EmailTemplate::STATUS_ACTIVE,
        'admin-notification' => EmailTemplate::STATUS_ACTIVE,
        'double-opt-in' => EmailTemplate::STATUS_ACTIVE,
        'newsletter-welcome' => EmailTemplate::STATUS_ACTIVE,
    ])
        ->and($this->defaults->installed())->toBeTrue();
});

it('gives an upgraded site the defaults as drafts, and a default added later the same', function () {
    $this->defaults->install(false, 1, new DateTimeImmutable('2026-10-23'));
    $this->defaults->add('account-welcome', static fn (): DefaultEmailTemplate => new DefaultEmailTemplate(
        'account-welcome',
        'Welcome to your account',
        EmailKind::ACCOUNT,
        'transactional',
        'Welcome',
        '<p>Welcome, {{ user.name }}.</p>',
    ));

    expect($this->defaults->installed())->toBeFalse();
    // Asked again on a later request, as if the site were new: what it was is kept.
    $this->defaults->install(true, 1, new DateTimeImmutable('2026-10-23'));

    expect(array_unique(shippedStatuses($this->templates)))->toBe(['contact-confirmation' => EmailTemplate::STATUS_DRAFT])
        ->and(shippedStatuses($this->templates))->toHaveKey('account-welcome');
});

it('leaves alone a template of the site\'s own that has a default\'s slug, and makes each default once', function () {
    $own = $this->templates->create('double-opt-in', 'Our own opt-in', 1, new DateTimeImmutable('2026-10-23'));

    $this->defaults->install(true, 1, new DateTimeImmutable('2026-10-23'));
    $this->defaults->install(true, 1, new DateTimeImmutable('2026-10-23'));

    expect($this->templates->findBySlug('double-opt-in')?->name)->toBe('Our own opt-in')
        ->and($this->templates->versions($own->id))->toBe([])
        ->and(count($this->templates->all()))->toBe(4);
});

it('ships defaults that use only variables CoreX knows, and draws each in the frame', function (string $slug) {
    $this->defaults->install(true, 1, new DateTimeImmutable('2026-10-23'));
    $template = $this->templates->findBySlug($slug);
    $version = $this->templates->findVersion($template->id, $template->activeVersion);

    $drawn = $this->editor->render($version, ['site' => ['name' => 'Acme']], null, $this->defaults->definition($slug)->kind);

    expect($this->editor->validateDraft($version))->toBe([])
        ->and($drawn['html'])->toContain('This message was sent by')
        ->and($drawn['html'])->not->toContain('{{')
        ->and($drawn['plain'])->not->toBe('');
})->with(['contact-confirmation', 'admin-notification', 'double-opt-in', 'newsletter-welcome']);

it('puts a default back as shipped, as a new active version, with the edit kept in its history', function () {
    $this->defaults->install(true, 1, new DateTimeImmutable('2026-10-23'));
    $template = $this->templates->findBySlug('admin-notification');
    $shipped = $this->templates->findVersion($template->id, 1);

    $version = $this->defaults->reset($template->id, 1, new DateTimeImmutable('2026-10-24'));

    expect($version->versionNumber)->toBe(2)
        ->and($version->htmlBody)->toBe($shipped->htmlBody)
        ->and($this->templates->find($template->id)?->activeVersion)->toBe(2)
        ->and(count($this->templates->versions($template->id)))->toBe(2)
        ->and(fn () => $this->defaults->reset(999, 1, new DateTimeImmutable('2026-10-24')))->toThrow(DomainException::class);
});
