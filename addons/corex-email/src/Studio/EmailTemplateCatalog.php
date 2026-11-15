<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use Corex\Mail\MailTemplateCatalog;

/**
 * Projects active Email Studio templates for cross-module binding controls, each with the
 * variables its active version uses (spec 120), so that a flow can say which it does not supply.
 */
final readonly class EmailTemplateCatalog implements MailTemplateCatalog
{
    public function __construct(
        private EmailTemplateRepository $templates,
        private ?EmailTemplateService $editor = null,
    ) {
    }

    public function templates(): array
    {
        $active = array_filter(
            $this->templates->all(),
            static fn (EmailTemplate $template): bool => $template->status === EmailTemplate::STATUS_ACTIVE,
        );

        return array_values(array_map(
            fn (EmailTemplate $template): array => [
                'id' => $template->id,
                'slug' => $template->slug,
                'name' => $template->name,
                'variables' => $this->variablesOf($template),
            ],
            $active,
        ));
    }

    /** @return list<string> */
    private function variablesOf(EmailTemplate $template): array
    {
        $version = $this->templates->findVersion($template->id, $template->activeVersion);

        return $version === null || $this->editor === null ? [] : $this->editor->variablesOf($version);
    }
}
