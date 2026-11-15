<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Admin;

defined('ABSPATH') || exit;

use Corex\Assistant\AssistantSettings;
use Corex\Assistant\Booking\BookingHours;
use Corex\Assistant\Chain\Cooldowns;
use Corex\Assistant\Chain\Provider;

/**
 * What a save of the Assistant screen writes (spec 129, FR-019): each part of the posted form cleaned and
 * stored. The switch, the keys and the models are Config values, which the Config engine reads only from
 * options WordPress loads with every request (DECISIONS #361), so they are stored that way, as every CoreX
 * setting is. The services, limits, words and hours are arrays only the assistant reads, stored with
 * autoload off.
 *
 * Every cleaning step reads its own output back unchanged, so a value saved twice is saved the same. A
 * key the site's `.env` sets is never written. A key field left empty keeps the saved key; a new key
 * gives its service a fresh try, whatever the old one did.
 */
final class AssistantScreenSave
{
    /** A key is printable ASCII only: a text cleaner would damage one that holds `%xx`. */
    private const KEY_PATTERN = '/[^!-~]/';

    /**
     * @param \Closure(string, mixed, bool): void  $write   option name, value, whether WordPress loads it with every request
     * @param \Closure(string): void               $remove  deletes an option
     * @param \Closure(string): bool               $locked  whether `.env` sets this Config key
     * @param \Closure(): list<string>             $languages the languages the owner writes copy for
     */
    public function __construct(
        private readonly Cooldowns $cooldowns,
        private readonly \Closure $write,
        private readonly \Closure $remove,
        private readonly \Closure $locked,
        private readonly \Closure $languages,
        private readonly string $siteZone,
    ) {
    }

    /**
     * @param array<string, mixed> $posted the form, unslashed
     */
    public function save(array $posted): void
    {
        if (! ($this->locked)(AssistantSettings::ENABLED)) {
            ($this->write)(self::option(AssistantSettings::ENABLED), ! empty($posted['enabled']) ? '1' : '0', true);
        }
        $this->keysAndModels(is_array($posted['services'] ?? null) ? $posted['services'] : []);
        ($this->write)(AssistantSettings::SERVICES_OPTION, $this->services($posted), false);
        ($this->write)(AssistantSettings::LIMITS_OPTION, self::limits($posted['limits'] ?? []), false);
        ($this->write)(AssistantSettings::BRAND_OPTION, $this->brand($posted['brand'] ?? []), false);
        ($this->write)(AssistantSettings::BOOKING_OPTION, $this->booking($posted['booking'] ?? []), false);
    }

    /** @param array<string, mixed> $services */
    private function keysAndModels(array $services): void
    {
        foreach (array_keys(Provider::CATALOGUE) as $id) {
            $row = is_array($services[$id] ?? null) ? $services[$id] : [];
            $keyField = AssistantSettings::keyField($id);
            if (! ($this->locked)($keyField)) {
                $key = (string) preg_replace(self::KEY_PATTERN, '', is_string($row['key'] ?? null) ? $row['key'] : '');
                if (! empty($row['remove'])) {
                    ($this->remove)(self::option($keyField));
                    $this->cooldowns->clear($id);
                } elseif ($key !== '') {
                    ($this->write)(self::option($keyField), $key, true);
                    $this->cooldowns->clear($id);
                }
            }

            $modelField = AssistantSettings::modelField($id);
            if (! ($this->locked)($modelField)) {
                $model = trim(sanitize_text_field(is_string($row['model'] ?? null) ? $row['model'] : ''));
                $model === '' ? ($this->remove)(self::option($modelField)) : ($this->write)(self::option($modelField), $model, true);
            }
        }
    }

    /**
     * The order the rows were posted in, a move asked for without scripts applied to it, and the
     * services switched off.
     *
     * @param array<string, mixed> $posted
     * @return array{order: list<string>, off: list<string>}
     */
    private function services(array $posted): array
    {
        $rows = is_array($posted['services'] ?? null) ? $posted['services'] : [];
        $known = array_keys(Provider::CATALOGUE);
        $order = array_values(array_unique([...array_intersect(array_keys($rows), $known), ...$known]));
        $order = self::moved($order, is_string($posted['move'] ?? null) ? $posted['move'] : '');
        $off = array_values(array_filter($known, static fn (string $id): bool => empty($rows[$id]['on'])));

        return ['order' => $order, 'off' => $off];
    }

    /**
     * `gemini:up` or `groq:down`, from a row's button when the page has no script to move it.
     *
     * @param list<string> $order
     * @return list<string>
     */
    public static function moved(array $order, string $move): array
    {
        [$id, $way] = array_pad(explode(':', $move, 2), 2, '');
        $at = array_search($id, $order, true);
        if ($at === false) {
            return $order;
        }
        $to = $way === 'up' ? $at - 1 : ($way === 'down' ? $at + 1 : $at);
        if ($to < 0 || $to >= count($order) || $to === $at) {
            return $order;
        }
        [$order[$at], $order[$to]] = [$order[$to], $order[$at]];

        return $order;
    }

    /** @return array{hour: int, day: int, site: int, paid: int} */
    public static function limits(mixed $posted): array
    {
        $posted = is_array($posted) ? $posted : [];
        $limits = AssistantSettings::DEFAULT_LIMITS;
        foreach (array_keys($limits) as $name) {
            if (isset($posted[$name]) && is_numeric($posted[$name])) {
                $limits[$name] = min(100000, max(0, (int) $posted[$name]));
            }
        }

        return $limits;
    }

    /** @return array{icon: string, copy: array<string, array{name: string, welcome: string, suggestions: list<string>}>} */
    private function brand(mixed $posted): array
    {
        $posted = is_array($posted) ? $posted : [];
        $copy = [];
        foreach (($this->languages)() as $language) {
            $row = is_array($posted['copy'][$language] ?? null) ? $posted['copy'][$language] : [];
            $suggestions = is_array($row['suggestions'] ?? null) ? $row['suggestions'] : [];
            $copy[$language] = [
                'name' => sanitize_text_field(is_string($row['name'] ?? null) ? $row['name'] : ''),
                'welcome' => sanitize_textarea_field(is_string($row['welcome'] ?? null) ? $row['welcome'] : ''),
                'suggestions' => array_slice(array_values(array_filter(
                    array_map(static fn (mixed $item): string => sanitize_text_field(is_string($item) ? $item : ''), $suggestions),
                    static fn (string $item): bool => $item !== '',
                )), 0, AssistantSettings::MAX_SUGGESTIONS),
            ];
        }

        return ['icon' => esc_url_raw(is_string($posted['icon'] ?? null) ? trim($posted['icon']) : ''), 'copy' => $copy];
    }

    /** @return array<string, mixed> */
    private function booking(mixed $posted): array
    {
        $posted = is_array($posted) ? $posted : [];
        // A switch and day boxes that are not ticked are not posted: absent is "off" and "no day".
        $posted['enabled'] = ! empty($posted['enabled']);
        $posted['days'] = is_array($posted['days'] ?? null) ? $posted['days'] : [];

        return BookingHours::fromArray($posted, $this->siteZone)->toArray();
    }

    private static function option(string $configKey): string
    {
        return 'corex_' . str_replace('.', '_', $configKey);
    }
}
