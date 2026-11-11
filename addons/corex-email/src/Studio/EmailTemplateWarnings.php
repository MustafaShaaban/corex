<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use Corex\Email\Design\EmailKind;
use Corex\Email\Routing\EmailRouteRepository;
use Corex\Mail\MailTemplateUsage;

/**
 * What a template saves with but should be told about (spec 120): a variable an email it is
 * bound to does not supply, and a From address on another domain than the site's.
 */
final readonly class EmailTemplateWarnings
{
    public function __construct(
        private EmailTemplateService $editor,
        private DefaultEmailTemplates $defaults,
        private EmailRouteRepository $routes,
        /** Asked which of a form's emails bind the template, where corex-forms is active. */
        private ?MailTemplateUsage $usage = null,
    ) {
    }

    /** @return array<string,string> By field: `variables`, `fromAddress`. */
    public function of(EmailTemplate $template, EmailTemplateVersion $version): array
    {
        $warnings = [];
        $unsupplied = $this->editor->unsupplied($version, $this->emailsSending($template));
        if ($unsupplied !== []) {
            $warnings['variables'] = implode(' ', $unsupplied);
        }
        $domain = $this->otherDomain($version->fromAddress);
        if ($domain !== '') {
            $warnings['fromAddress'] = sprintf(
                /* translators: 1: configured From domain, 2: site domain. */
                __('Your From address is on %1$s, a different domain from this site (%2$s). Some providers rewrite or flag mail whose domain is not authorised to send. Confirm your transport plugin is allowed to send as this domain.', 'corex'),
                $domain,
                (string) wp_parse_url((string) home_url(), PHP_URL_HOST),
            );
        }

        return $warnings;
    }

    /**
     * The kinds of email that send this template: its own kind for a template CoreX ships, its
     * routes' triggers, and the flows that bind it.
     *
     * @return list<string> {@see EmailVariableCatalog} groups.
     */
    private function emailsSending(EmailTemplate $template): array
    {
        $groups = [];
        $kind = $this->defaults->definition($template->slug)?->kind;
        $groups[] = match ($kind) {
            EmailKind::NOTIFICATION, EmailKind::CONFIRMATION => EmailVariableCatalog::FORM,
            EmailKind::NEWSLETTER => EmailVariableCatalog::NEWSLETTER,
            EmailKind::ACCOUNT => EmailVariableCatalog::ACCOUNT,
            default => null,
        };
        foreach ($this->routes->all() as $route) {
            if ($route->templateId === $template->id) {
                $groups[] = match (true) {
                    str_starts_with($route->trigger, 'forms.') => EmailVariableCatalog::FORM,
                    str_starts_with($route->trigger, 'access.') => EmailVariableCatalog::ACCESS,
                    default => null,
                };
            }
        }
        if ($this->usage !== null && in_array(MailTemplateUsage::FORM, $this->usage->emailsUsing($template->id), true)) {
            $groups[] = EmailVariableCatalog::FORM;
        }

        return array_values(array_unique(array_filter($groups, 'is_string')));
    }

    /** The From address's domain where it is not the site's, else ''. */
    private function otherDomain(string $address): string
    {
        $site = strtolower((string) wp_parse_url((string) home_url(), PHP_URL_HOST));
        $domain = str_contains($address, '@') ? strtolower(substr((string) strrchr($address, '@'), 1)) : '';

        return $domain !== '' && $site !== '' && $domain !== $site ? $domain : '';
    }
}
