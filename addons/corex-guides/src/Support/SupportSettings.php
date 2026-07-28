<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides\Support;

defined('ABSPATH') || exit;

use Corex\Support\Config\ConfigInterface;

/**
 * Where a support message goes, and whether the form is offered at all (spec 087, FR-002 / FR-003).
 *
 * Two layers, and the order matters. `config/guides.php` holds the shipped default, which is the
 * one line a developer edits. The `corex_guides_support_email` option, written by the Settings
 * screen, layers over it — so a site owner changes the address without a deploy, and a site that has
 * never opened Settings still has a working address.
 *
 * The layering is not implemented here: the Config engine already does it (`.env` → options →
 * defaults, first source wins). This class only supplies the defaults the engine has no other way to
 * learn, because {@see \Corex\Foundation\CoreServiceProvider::defaults()} globs `corex-core/config/`
 * and nothing else — an add-on's config file is not auto-discovered.
 */
final class SupportSettings
{
    public const EMAIL_KEY   = 'guides.support.email';
    public const ENABLED_KEY = 'guides.support.enabled';

    /** @var array<string,mixed>|null */
    private static ?array $fileDefaults = null;

    public function __construct(private readonly ConfigInterface $config)
    {
    }

    /**
     * The address a support message is sent to. Empty when the site has deliberately blanked it.
     */
    public function recipient(): string
    {
        $value = $this->config->get(self::EMAIL_KEY, self::defaults()['email']);
        $value = is_string($value) ? trim($value) : '';

        // A stored value that is not a deliverable address is treated as no address at all. The
        // panel then says support is not set up, which is true, rather than accepting a message the
        // mailer would silently drop.
        return is_email($value) ? $value : '';
    }

    /** Whether the site wants the support form offered at all. */
    public function enabled(): bool
    {
        $value = $this->config->get(self::ENABLED_KEY, self::defaults()['enabled']);

        // Checkbox settings arrive from the options layer as '1' / '' rather than as booleans.
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /** Whether a message sent right now would have somewhere to go. */
    public function configured(): bool
    {
        return $this->enabled() && $this->recipient() !== '';
    }

    /** @return array{email:string,enabled:bool} */
    private static function defaults(): array
    {
        if (self::$fileDefaults === null) {
            $file = dirname(__DIR__, 2) . '/config/guides.php';
            $loaded = is_file($file) ? require $file : [];
            $support = is_array($loaded) ? (array) ($loaded['support'] ?? []) : [];

            self::$fileDefaults = [
                'email'   => is_string($support['email'] ?? null) ? $support['email'] : '',
                'enabled' => (bool) ($support['enabled'] ?? false),
            ];
        }

        /** @var array{email:string,enabled:bool} */
        return self::$fileDefaults;
    }
}
