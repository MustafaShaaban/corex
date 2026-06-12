<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

defined('ABSPATH') || exit;

/**
 * The set of kits enabled but not yet applied or dismissed — the state that drives the activation prompt
 * (spec 042). A thin wrapper over one option so the Add-ons screen (which adds a kit on enable) and the
 * activation notice (which removes it on apply/dismiss) share one source of truth, not duplicated array logic.
 */
final class PendingKits
{
    private const OPTION = 'corex_kit_pending';

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return array_values(array_map('strval', (array) get_option(self::OPTION, [])));
    }

    public function add(string $kit): void
    {
        $pending = $this->all();

        if (! in_array($kit, $pending, true)) {
            $pending[] = $kit;
            update_option(self::OPTION, array_values($pending));
        }
    }

    public function remove(string $kit): void
    {
        update_option(self::OPTION, array_values(array_filter(
            $this->all(),
            static fn (string $name): bool => $name !== $kit,
        )));
    }
}
