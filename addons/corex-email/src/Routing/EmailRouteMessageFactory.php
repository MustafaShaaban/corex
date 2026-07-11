<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Routing;

defined('ABSPATH') || exit;

use Corex\Email\Message\EmailMessage;
use Corex\Email\Studio\EmailLayoutRepository;
use Corex\Email\Studio\EmailTemplateReference;
use Corex\Email\Studio\EmailTemplateRepository;
use Corex\Email\Studio\EmailTemplateService;

/**
 * Prepares the active immutable template revision selected by a route.
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

        $context  = $this->withSiteContext($context);
        $layout   = $this->layouts?->findVersion($version->layoutId, $version->layoutVersion);
        $rendered = $this->renderer->render($version, $context, $layout);

        return new PreparedEmail(
            new EmailMessage(
                $route->recipients,
                [],
                [],
                $route->replyTo,
                $rendered['subject'],
                $rendered['html'],
                ['Content-Type' => 'text/html; charset=UTF-8'],
            ),
            new EmailTemplateReference(
                $template->slug,
                $template->id,
                $version->versionNumber,
                $route->routeId,
            ),
        );
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function withSiteContext(array $context): array
    {
        if (isset($context['site']) || ! function_exists('get_bloginfo')) {
            return $context;
        }

        $context['site'] = [
            'name' => get_bloginfo('name'),
            'url'  => function_exists('home_url') ? home_url('/') : '',
        ];

        return $context;
    }
}
