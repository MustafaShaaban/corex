<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use Corex\Mail\MailTemplateUsage;

/**
 * Whether a flow binds an Email Studio template to one of its emails (spec 120, story 5), for
 * the warnings Email Studio gives when a template uses a variable a form's email does not supply.
 * A flow's draft and its published version both count: the draft is what will be published.
 */
final readonly class FlowTemplateUsage implements MailTemplateUsage
{
    public function __construct(private FlowRepository $flows)
    {
    }

    public function emailsUsing(int $templateId): array
    {
        foreach ($this->flows->allWithDraft() as $entry) {
            foreach ($this->versionsOf($entry['flow'], $entry['version']) as $version) {
                if ($this->binds($version, $templateId)) {
                    return [self::FORM];
                }
            }
        }

        return [];
    }

    /**
     * A flow's draft, and its published version where that is another one: the one visitors are
     * sent emails from now.
     *
     * @return list<FlowVersion>
     */
    private function versionsOf(Flow $flow, FlowVersion $draft): array
    {
        $published = $flow->publishedVersion > 0 && $flow->publishedVersion !== $draft->versionNumber
            ? $this->flows->findVersion($flow->id, $flow->publishedVersion)
            : null;

        return $published === null ? [$draft] : [$draft, $published];
    }

    private function binds(FlowVersion $version, int $templateId): bool
    {
        foreach ((array) $version->configuration->emailRoutes as $binding) {
            if (is_array($binding) && (int) ($binding['template_id'] ?? 0) === $templateId) {
                return true;
            }
        }

        return false;
    }
}
