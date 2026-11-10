<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant;

defined('ABSPATH') || exit;

use Corex\Assistant\Booking\BookingHours;
use Corex\Assistant\Chain\Provider;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\Config\Truthy;

/**
 * Everything the owner sets for the assistant (spec 129): the switch, the services (key, model, order,
 * on or off), the limits, the branding per language, and the booking hours. Edited on CoreX → Assistant.
 *
 * The switch, the keys and the models are Config dot-keys, so the site's `.env` wins over the screen:
 * `assistant.groq.key` is `ASSISTANT_GROQ_KEY` there. The rest are arrays kept in options of their own,
 * stored with autoload off: nothing reads them but the chat, the button and the screen.
 */
final class AssistantSettings
{
    public const ENABLED = 'assistant.enabled';

    public const SERVICES_OPTION = 'corex_assistant_services';
    public const LIMITS_OPTION = 'corex_assistant_limits';
    public const BRAND_OPTION = 'corex_assistant_brand';
    public const BOOKING_OPTION = 'corex_assistant_booking';

    /** Questions a visitor may ask an hour and a day, questions site-wide a day, questions sent to paid services a day. */
    public const DEFAULT_LIMITS = ['hour' => 20, 'day' => 60, 'site' => 600, 'paid' => 50];

    /** Suggested questions shown under the welcome, at most. */
    public const MAX_SUGGESTIONS = 3;

    /**
     * @param \Closure(string, mixed): mixed $option   reads an option, with its default
     * @param string                        $siteZone the site's time zone, for booking hours never saved
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly \Closure $option,
        private readonly string $siteZone = 'UTC',
    ) {
    }

    public static function keyField(string $id): string
    {
        return 'assistant.' . $id . '.key';
    }

    public static function modelField(string $id): string
    {
        return 'assistant.' . $id . '.model';
    }

    public function enabled(): bool
    {
        return Truthy::of($this->config->get(self::ENABLED, false));
    }

    public function key(string $id): string
    {
        return $this->text(self::keyField($id));
    }

    /** The model the owner chose, else ''; the catalogue's applies then. */
    public function storedModel(string $id): string
    {
        return $this->text(self::modelField($id));
    }

    public function model(string $id): string
    {
        $model = $this->storedModel($id);

        return $model !== '' ? $model : (Provider::CATALOGUE[$id]['model'] ?? '');
    }

    /**
     * The services in the owner's order: the saved order first, then any the catalogue added since, in
     * the catalogue's order.
     *
     * @return list<string>
     */
    public function order(): array
    {
        $saved = $this->services()['order'] ?? [];
        $known = array_keys(Provider::CATALOGUE);

        return array_values(array_unique([...array_intersect(is_array($saved) ? $saved : [], $known), ...$known]));
    }

    /**
     * Whether the owner switched the service on. A service the owner never saw on the screen (none saved
     * yet, or one a later release added) is on if it is free and off if it is paid: a paid key alone
     * never spends money.
     */
    public function isOn(string $id): bool
    {
        $saved = $this->services();
        $seen = is_array($saved['order'] ?? null) && in_array($id, $saved['order'], true);
        if (! $seen || ! is_array($saved['off'] ?? null)) {
            return ! (Provider::CATALOGUE[$id]['paid'] ?? true);
        }

        return ! in_array($id, $saved['off'], true);
    }

    /** Whether anything can answer: on, and at least one service on with a key. Read on public pages. */
    public function live(): bool
    {
        if (! $this->enabled()) {
            return false;
        }
        foreach ($this->order() as $id) {
            if ($this->isOn($id) && $this->key($id) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * The services that are on and have a key, in the owner's order.
     *
     * @return list<Provider>
     */
    public function chain(): array
    {
        return array_values(array_filter($this->keyed(), fn (Provider $provider): bool => $this->isOn($provider->id)));
    }

    /**
     * Every service with a key, on or off, in the owner's order: what "Check services" asks, so a key can
     * be tried before its service is switched on.
     *
     * @return list<Provider>
     */
    public function keyed(): array
    {
        $keyed = [];
        foreach ($this->order() as $id) {
            $key = $this->key($id);
            if ($key !== '') {
                $keyed[] = Provider::fromCatalogue($id, $key, $this->storedModel($id));
            }
        }

        return $keyed;
    }

    /** @return array{hour: int, day: int, site: int, paid: int} */
    public function limits(): array
    {
        $saved = ($this->option)(self::LIMITS_OPTION, []);
        $limits = self::DEFAULT_LIMITS;
        foreach (array_keys($limits) as $name) {
            if (is_array($saved) && isset($saved[$name]) && is_numeric($saved[$name])) {
                $limits[$name] = max(0, (int) $saved[$name]);
            }
        }

        return $limits;
    }

    /**
     * What the owner wrote over the built-in copy for a language: name, welcome, suggestions. A field
     * left empty is absent, so the built-in copy (or the site's filter) applies.
     *
     * @return array{name?: string, welcome?: string, suggestions?: list<string>}
     */
    public function brandCopy(string $language): array
    {
        $saved = ($this->option)(self::BRAND_OPTION, []);
        $copy = is_array($saved) && is_array($saved['copy'][$language] ?? null) ? $saved['copy'][$language] : [];
        $out = [];
        foreach (['name', 'welcome'] as $field) {
            $text = is_string($copy[$field] ?? null) ? trim($copy[$field]) : '';
            if ($text !== '') {
                $out[$field] = $text;
            }
        }
        $suggestions = array_values(array_filter(
            array_map(static fn (mixed $item): string => is_string($item) ? trim($item) : '', (array) ($copy['suggestions'] ?? [])),
            static fn (string $item): bool => $item !== '',
        ));
        if ($suggestions !== []) {
            $out['suggestions'] = array_slice($suggestions, 0, self::MAX_SUGGESTIONS);
        }

        return $out;
    }

    /** The image the owner chose for the button (an address), or '' for the built-in icon. */
    public function icon(): string
    {
        $saved = ($this->option)(self::BRAND_OPTION, []);

        return is_array($saved) && is_string($saved['icon'] ?? null) ? trim($saved['icon']) : '';
    }

    public function booking(): BookingHours
    {
        $saved = ($this->option)(self::BOOKING_OPTION, []);

        return BookingHours::fromArray(is_array($saved) ? $saved : [], $this->siteZone);
    }

    /** @return array<string, mixed> */
    private function services(): array
    {
        $saved = ($this->option)(self::SERVICES_OPTION, []);

        return is_array($saved) ? $saved : [];
    }

    private function text(string $key): string
    {
        $value = $this->config->get($key, '');

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
