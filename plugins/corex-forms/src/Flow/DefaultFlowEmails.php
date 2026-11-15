<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use Corex\Mail\MailTemplateCatalog;

/**
 * The emails a new flow starts with (spec 120, story 4): "Admin notification" to the site's
 * administrator and "Contact confirmation" to whoever fills the form in, each switched on and
 * each switchable off. Only templates that are active are bound, so a site whose defaults are
 * drafts (an upgraded one) starts a flow with no email, as before.
 */
final readonly class DefaultFlowEmails
{
    public const TEAM_TEMPLATE = 'admin-notification';

    public const SUBMITTER_TEMPLATE = 'contact-confirmation';

    public function __construct(private ?MailTemplateCatalog $templates = null)
    {
    }

    /** @return list<array<string,mixed>> */
    public function bindings(): array
    {
        $active = [];
        foreach ($this->templates?->templates() ?? [] as $template) {
            $active[(string) ($template['slug'] ?? '')] = (int) ($template['id'] ?? 0);
        }

        $bindings = [];
        $admin = (string) get_option('admin_email');
        if (($active[self::TEAM_TEMPLATE] ?? 0) > 0 && is_email($admin) !== false) {
            $bindings[] = [
                'event'       => 'team_notification',
                'enabled'     => true,
                'template_id' => $active[self::TEAM_TEMPLATE],
                'recipient'   => $admin,
                // So the team can answer with Reply, when the form asks for an address.
                'reply_to'    => 'email',
            ];
        }
        if (($active[self::SUBMITTER_TEMPLATE] ?? 0) > 0) {
            $bindings[] = [
                'event'       => 'submitter_confirmation',
                'enabled'     => true,
                'template_id' => $active[self::SUBMITTER_TEMPLATE],
                'recipient'   => 'email',
                'reply_to'    => '',
            ];
        }

        return $bindings;
    }
}
