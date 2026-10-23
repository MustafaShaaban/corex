<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Routing;

defined('ABSPATH') || exit;

use Corex\Email\Design\EmailKind;
use Corex\Email\Message\EmailMessage;
use Corex\Email\Studio\EmailLayoutRepository;
use Corex\Email\Studio\EmailTemplateReference;
use Corex\Email\Studio\EmailTemplateRepository;
use Corex\Email\Studio\EmailTemplateService;
use Corex\Email\Studio\EmailTemplateVersion;

/**
 * Prepares the active immutable template revision selected by a route: drawn in the frame, from
 * the template's own sender, with its plain text (spec 120, story 2).
 */
final readonly class EmailRouteMessageFactory
{
    public function __construct(
        private EmailTemplateRepository $templates,
        private EmailTemplateService $renderer,
        private ?EmailLayoutRepository $layouts = null,
    ) {
    }

    /** @param array<string,mixed> $context */
    public function prepare(ResolvedEmailRoute $route, array $context): ?PreparedEmail
    {
        $template = $this->templates->find($route->templateId);
        if ($template === null || $template->activeVersion < 1) {
            return null;
        }

        $version = $this->templates->findVersion($template->id, $template->activeVersion);
        if ($version === null) {
            return null;
        }

        $context  = self::withSiteContext($context, $route->recipients);
        $layout   = $this->layouts?->findVersion($version->layoutId, $version->layoutVersion);
        $rendered = $this->renderer->render($version, $context, $layout, EmailKind::of($context['email']['kind'] ?? null));

        return new PreparedEmail(
            self::message($version, $route->recipients, $route->replyTo, $rendered),
            new EmailTemplateReference(
                $template->slug,
                $template->id,
                $version->versionNumber,
                $route->routeId,
                $rendered['missing'],
            ),
        );
    }

    /**
     * The email a drawn template version is sent as: from the template's own sender where it
     * names one, Settings → Mail's otherwise, with its plain text as the text/plain part.
     *
     * @param list<string>                                                          $to
     * @param array{subject:string,html:string,plain:string,missing:list<string>} $rendered
     */
    public static function message(EmailTemplateVersion $version, array $to, ?string $replyTo, array $rendered): EmailMessage
    {
        return new EmailMessage(
            $to,
            [],
            [],
            $replyTo,
            $rendered['subject'],
            $rendered['html'],
            ['Content-Type' => 'text/html; charset=UTF-8'],
            from: $version->fromAddress !== '' ? $version->fromAddress : null,
            plainText: $rendered['plain'] !== '' ? $rendered['plain'] : null,
            fromName: trim($version->fromName) !== '' ? $version->fromName : null,
        );
    }

    /**
     * What every email is given to draw from: the site, its privacy page, and who it is for.
     *
     * @param array<string,mixed> $context
     * @param list<string>        $recipients
     *
     * @return array<string,mixed>
     */
    public static function withSiteContext(array $context, array $recipients = []): array
    {
        if (! function_exists('get_bloginfo')) {
            return $context;
        }

        // WordPress keeps the name with its entities encoded; a value is escaped where it lands.
        $context['site'] = (is_array($context['site'] ?? null) ? $context['site'] : []) + [
            'name' => html_entity_decode((string) get_bloginfo('name'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'url'  => function_exists('home_url') ? (string) home_url('/') : '',
        ];
        if (is_array($context['links'] ?? []) && function_exists('get_privacy_policy_url')) {
            $context['links'] = ($context['links'] ?? []) + ['privacy' => (string) get_privacy_policy_url()];
        }
        if (is_array($context['recipient'] ?? []) && $recipients !== []) {
            $context['recipient'] = ($context['recipient'] ?? []) + ['email' => $recipients[0]];
        }

        return $context;
    }
}
