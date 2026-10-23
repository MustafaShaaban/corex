<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use Closure;
use Corex\Email\Design\EmailKind;
use Corex\Support\Options\LoadedOptions;
use DateTimeImmutable;
use DomainException;

/**
 * The Email Studio templates CoreX ships, and their installation (spec 120, story 4).
 *
 * A site that had no Email Studio defaults before this release starts with them **active**; a
 * site that is upgraded gets them as **drafts**, bound to nothing, so nothing it sends changes.
 * Which of the two a site is, is kept, so that a default an add-on adds later (the account
 * add-on's two) follows the same rule. A slug that a template of the site's own already uses is
 * left alone. Each default's words are written when it is installed, in the site's language.
 */
final class DefaultEmailTemplates
{
    /** `active` or `draft`: what this site's defaults are installed as. */
    public const STATE_OPTION = 'corex_email_default_templates_state';

    /** The slugs already installed (or found taken), so that a default is made once. */
    public const INSTALLED_OPTION = 'corex_email_default_templates';

    public const ACTIVE = 'active';
    public const DRAFT  = 'draft';

    /** @var array<string,Closure():DefaultEmailTemplate> */
    private array $added = [];

    public function __construct(
        private readonly EmailTemplateRepository $templates,
        private readonly EmailLayoutRepository $layouts,
        private readonly EmailTemplateService $editor,
    ) {
    }

    /**
     * Adds a default an add-on ships, written when it is installed or reset.
     *
     * @param Closure():DefaultEmailTemplate $definition
     */
    public function add(string $slug, Closure $definition): void
    {
        $this->added[$slug] = $definition;
    }

    /** @return list<string> */
    public function slugs(): array
    {
        return [...array_keys($this->shipped()), ...array_keys($this->added)];
    }

    public function isDefault(string $slug): bool
    {
        return in_array($slug, $this->slugs(), true);
    }

    public function definition(string $slug): ?DefaultEmailTemplate
    {
        $definition = $this->shipped()[$slug] ?? $this->added[$slug] ?? null;

        return $definition === null ? null : $definition();
    }

    /** Whether every default has been installed already: nothing to do on this request. */
    public function installed(): bool
    {
        return array_diff($this->slugs(), $this->installedSlugs()) === [];
    }

    /**
     * Installs each default that is not installed yet.
     *
     * @param bool $newSite Whether the site had no Email Studio defaults before: asked once, and
     *                      kept, the first time this runs.
     */
    public function install(bool $newSite, int $actorId, DateTimeImmutable $now): void
    {
        $state = (string) get_option(self::STATE_OPTION, '');
        if (! in_array($state, [self::ACTIVE, self::DRAFT], true)) {
            $state = $newSite ? self::ACTIVE : self::DRAFT;
            LoadedOptions::store(self::STATE_OPTION, $state);
        }

        $installed = $this->installedSlugs();
        foreach ($this->slugs() as $slug) {
            if (in_array($slug, $installed, true)) {
                continue;
            }
            if ($this->templates->findBySlug($slug) === null) {
                $definition = $this->definition($slug);
                if ($definition === null || ! $this->create($definition, $state === self::ACTIVE, $actorId, $now)) {
                    continue;
                }
            }
            $installed[] = $slug;
        }

        LoadedOptions::store(self::INSTALLED_OPTION, array_values(array_unique($installed)));
    }

    /**
     * Puts a default back as it was shipped: a new version, active when the template is, with the
     * edited ones kept in its history.
     */
    public function reset(int $templateId, int $actorId, DateTimeImmutable $now): EmailTemplateVersion
    {
        $template = $this->templates->find($templateId);
        $definition = $template === null ? null : $this->definition($template->slug);
        if ($template === null || $definition === null) {
            throw new DomainException(__('Only an email template CoreX ships can be reset.', 'corex'));
        }

        $version = $this->saveShipped($template, $definition, $actorId, $now);
        if ($template->status === EmailTemplate::STATUS_ACTIVE) {
            $this->templates->activate($template->id, $version->versionNumber, $actorId, $now);
        }

        return $version;
    }

    private function create(DefaultEmailTemplate $definition, bool $active, int $actorId, DateTimeImmutable $now): bool
    {
        if ($this->layouts->find($definition->layoutSlug) === null) {
            return false;
        }
        $template = $this->templates->create($definition->slug, $definition->name, $actorId, $now);
        $version = $this->saveShipped($template, $definition, $actorId, $now);
        if ($active) {
            $this->templates->activate($template->id, $version->versionNumber, $actorId, $now);
        }

        return true;
    }

    private function saveShipped(EmailTemplate $template, DefaultEmailTemplate $definition, int $actorId, DateTimeImmutable $now): EmailTemplateVersion
    {
        $layout = $this->layouts->find($definition->layoutSlug)
            ?? throw new DomainException(__('The layout this template is arranged by is not available.', 'corex'));
        $content = [
            'subject'  => $definition->subject,
            'htmlBody' => $definition->htmlBody,
            'layout'   => $layout->id . ':' . $layout->version,
        ];
        return $this->templates->saveDraft(new EmailTemplateVersion(
            id: 1,
            templateId: $template->id,
            versionNumber: max(1, $template->draftVersion + 1),
            subject: $definition->subject,
            fromName: '',
            fromAddress: '',
            htmlBody: $definition->htmlBody,
            plainText: '',
            plainTextMode: EmailTemplateVersion::PLAIN_AUTO,
            layoutId: $layout->id,
            layoutVersion: $layout->version,
            variableKeys: $this->editor->variablesIn($definition->subject, $definition->htmlBody, ''),
            createdBy: $actorId,
            createdAt: $now,
            checksum: hash('sha256', serialize($content)),
        ));
    }

    /** @return list<string> */
    private function installedSlugs(): array
    {
        $installed = get_option(self::INSTALLED_OPTION, []);

        return is_array($installed) ? array_values(array_filter($installed, 'is_string')) : [];
    }

    /** @return array<string,Closure():DefaultEmailTemplate> */
    private function shipped(): array
    {
        return [
            'contact-confirmation' => static fn (): DefaultEmailTemplate => new DefaultEmailTemplate(
                'contact-confirmation',
                __('Contact confirmation', 'corex'),
                EmailKind::CONFIRMATION,
                'transactional',
                __('Thanks, we have your message', 'corex'),
                '<h1>' . esc_html__('Thanks, we have your message.', 'corex') . '</h1>'
                /* translators: %s: the visitor's name, filled in when the email is sent. */
                . '<p>' . sprintf(esc_html__('Hi %s, thanks for reaching out. A member of the team will reply soon. Here is a copy of what you sent:', 'corex'), '{{ submission.name }}') . '</p>'
                . '{{ form.answers }}'
                /* translators: %s: the site's name, filled in when the email is sent. */
                . '<p>' . esc_html__('Warm regards,', 'corex') . '<br />' . sprintf(esc_html__('The %s team', 'corex'), '{{ site.name }}') . '</p>',
            ),
            'admin-notification' => static fn (): DefaultEmailTemplate => new DefaultEmailTemplate(
                'admin-notification',
                __('Admin notification', 'corex'),
                EmailKind::NOTIFICATION,
                'transactional',
                /* translators: %s: the form's title, filled in when the email is sent. */
                sprintf(__('New submission: %s', 'corex'), '{{ form.title }}'),
                /* translators: %s: the form's title, filled in when the email is sent. */
                '<h1>' . sprintf(esc_html__('New submission: %s', 'corex'), '{{ form.title }}') . '</h1>'
                /* translators: 1: the form's title. 2: the site's name. Both filled in when the email is sent. */
                . '<p>' . sprintf(esc_html__('Someone filled in %1$s on %2$s. These are the answers:', 'corex'), '{{ form.title }}', '{{ site.name }}') . '</p>'
                . '{{ form.answers }}'
                . '<p><a class="button" href="{{ links.submission }}">' . esc_html__('Open the submission', 'corex') . '</a></p>',
            ),
            'double-opt-in' => static fn (): DefaultEmailTemplate => new DefaultEmailTemplate(
                'double-opt-in',
                __('Double opt-in', 'corex'),
                EmailKind::NEWSLETTER,
                'newsletter',
                __('Confirm your subscription', 'corex'),
                '<p><strong>' . esc_html__('One last step', 'corex') . '</strong></p>'
                . '<h1>' . esc_html__('Confirm your subscription', 'corex') . '</h1>'
                /* translators: %s: the site's name, filled in when the email is sent. */
                . '<p>' . sprintf(esc_html__('You are nearly subscribed to updates from %s. To protect your inbox, please confirm that this address belongs to you.', 'corex'), '{{ site.name }}') . '</p>'
                . '<p><a class="button" href="{{ action.url }}">' . esc_html__('Confirm subscription', 'corex') . '</a></p>'
                . '<p>' . esc_html__('Button not working? Paste this link into your browser:', 'corex') . '<br />{{ action.url }}</p>'
                . '<p>' . esc_html__('If you did not sign up, you can ignore this email: no subscription is made.', 'corex') . '</p>',
            ),
            'newsletter-welcome' => static fn (): DefaultEmailTemplate => new DefaultEmailTemplate(
                'newsletter-welcome',
                __('Newsletter welcome', 'corex'),
                EmailKind::NEWSLETTER,
                'newsletter',
                /* translators: %s: the site's name, filled in when the email is sent. */
                sprintf(__('Welcome to %s', 'corex'), '{{ site.name }}'),
                '<p><strong>' . esc_html__('You are subscribed', 'corex') . '</strong></p>'
                /* translators: %s: the site's name, filled in when the email is sent. */
                . '<h1>' . sprintf(esc_html__('Welcome to %s.', 'corex'), '{{ site.name }}') . '</h1>'
                . '<p>' . esc_html__('Thanks for confirming. You will now get our updates by email.', 'corex') . '</p>'
                /* translators: %s: the site's name, filled in when the email is sent. */
                . '<p><a class="button" href="{{ site.url }}">' . sprintf(esc_html__('Visit %s', 'corex'), '{{ site.name }}') . '</a></p>',
            ),
        ];
    }
}
