<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use Corex\Mail\MailTemplateCatalog;

/**
 * Projects active Email Studio templates for cross-module binding controls.
 */
final readonly class EmailTemplateCatalog implements MailTemplateCatalog
{
    public function __construct(private EmailTemplateRepository $templates)
    {
    }

    public function templates(): array
    {
        $active = array_filter(
            $this->templates->all(),
            static fn (EmailTemplate $template): bool => $template->status === EmailTemplate::STATUS_ACTIVE,
        );

        return array_values(array_map(
            static fn (EmailTemplate $template): array => [
                'id' => $template->id,
                'slug' => $template->slug,
                'name' => $template->name,
            ],
            $active,
        ));
    }
}
