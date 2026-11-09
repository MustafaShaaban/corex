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
 * Everything the owner sets for the assistant (spec 129, FR-006, FR-017 to FR-020, FR-024, FR-025):
 * the switch, name and mark; per service a switch, a key and a model, and their order; the limits;
 * the booking hours; and per language the name, welcome and suggested questions.
 *
 * Every value is a Config dot-key, edited on Settings → Assistant and read through the Config engine,
 * so the site's `.env` wins over the screen: `assistant.gemini.key` is `ASSISTANT_GEMINI_KEY` there.
 * The keys are password fields of that screen, saved with autoload off.
 */
final class AssistantSettings
{
    public const ENABLED = 'assistant.enabled';
    public const NAME = 'assistant.name';
    public const MARK = 'assistant.mark';
    public const ORDER = 'assistant.order';
    public const DAILY_CAP = 'assistant.daily_cap';
    public const PAID_CAP = 'assistant.paid_cap';
    public const LIMIT_HOUR = 'assistant.limit.hour';
    public const LIMIT_DAY = 'assistant.limit.day';

    /**
     * What applies while nothing is stored. Settings → Assistant draws the same values before the
     * first save (`Corex\Config\Settings\SettingsFieldValues::DEFAULTS` mirrors this; a test holds
     * the two together). A service's switch defaults to whether it is free.
     */
    public const DEFAULTS = [
        self::LIMIT_HOUR => '20',
        self::LIMIT_DAY => '60',
        self::DAILY_CAP => '600',
        self::PAID_CAP => '50',
        'assistant.booking.days' => 'mon-fri',
        'assistant.booking.start' => '09:00',
        'assistant.booking.end' => '17:00',
        'assistant.booking.slot' => '30',
        'assistant.booking.notice' => '120',
        'assistant.booking.horizon' => '7',
    ];

    public function __construct(private readonly ConfigInterface $config)
    {
    }

    public static function keyField(string $id): string
    {
        return 'assistant.' . $id . '.key';
    }

    public static function modelField(string $id): string
    {
        return 'assistant.' . $id . '.model';
    }

    public static function switchField(string $id): string
    {
        return 'assistant.' . $id . '.on';
    }

    public function enabled(): bool
    {
        return Truthy::of($this->config->get(self::ENABLED, false));
    }

    /** The name Settings give the assistant; '' when none is set (the strings then name it). */
    public function name(): string
    {
        return $this->text(self::NAME);
    }

    /** The address of the image chosen as the assistant's mark; '' for the built-in icon. */
    public function mark(): string
    {
        return $this->text(self::MARK);
    }

    public function dailyCap(): int
    {
        return $this->count(self::DAILY_CAP);
    }

    public function paidCap(): int
    {
        return $this->count(self::PAID_CAP);
    }

    /** @return array{hour: int, day: int, site: int} what a visitor may ask an hour and a day, and the site a day */
    public function limits(): array
    {
        return ['hour' => $this->count(self::LIMIT_HOUR), 'day' => $this->count(self::LIMIT_DAY), 'site' => $this->dailyCap()];
    }

    public function key(string $id): string
    {
        return $this->text(self::keyField($id));
    }

    /** The model asked for, else the catalogue's. */
    public function model(string $id): string
    {
        $model = $this->text(self::modelField($id));

        return $model !== '' ? $model : (Provider::CATALOGUE[$id]['model'] ?? '');
    }

    /**
     * Whether the owner has the service switched on. Until a choice is saved, the free ones are on and
     * the paid ones off: a paid key alone never spends money.
     */
    public function isOn(string $id): bool
    {
        $stored = $this->text(self::switchField($id));

        return $stored === '' ? ! Provider::isPaid($id) : Truthy::of($stored);
    }

    /**
     * The services in the owner's order: the saved order's known ids, then any the catalogue holds
     * that it left out, in the catalogue's order.
     *
     * @return list<string>
     */
    public function order(): array
    {
        $known = array_keys(Provider::CATALOGUE);
        $saved = array_map('trim', explode(',', $this->text(self::ORDER)));

        return array_values(array_unique([...array_intersect($saved, $known), ...$known]));
    }

    /** Whether anything can answer: on, and at least one service on and keyed. Read on every public page. */
    public function live(): bool
    {
        return $this->enabled() && $this->chain() !== [];
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
     * Every service that has a key, on or off, in the owner's order: what "Check services" asks.
     *
     * @return list<Provider>
     */
    public function keyed(): array
    {
        $keyed = [];
        foreach ($this->order() as $id) {
            $key = $this->key($id);
            if ($key !== '') {
                $keyed[] = Provider::fromCatalogue($id, $key, $this->text(self::modelField($id)));
            }
        }

        return $keyed;
    }

    /**
     * What the owner wrote for a language over CoreX's words: name, welcome, and the three suggested
     * questions when all three are written. Blank ones are left out.
     *
     * @return array{name?: string, welcome?: string, suggestions?: list<string>}
     */
    public function brand(string $language): array
    {
        $prefix = 'assistant.brand.' . $language . '.';
        $brand = [];
        foreach (['name', 'welcome'] as $field) {
            $text = $this->text($prefix . $field);
            if ($text !== '') {
                $brand[$field] = $text;
            }
        }
        $suggestions = array_values(array_filter(
            [$this->text($prefix . 'suggestion1'), $this->text($prefix . 'suggestion2'), $this->text($prefix . 'suggestion3')],
            static fn (string $text): bool => $text !== '',
        ));
        if ($suggestions !== []) {
            $brand['suggestions'] = $suggestions;
        }

        return $brand;
    }

    public function bookingEnabled(): bool
    {
        return Truthy::of($this->config->get('assistant.booking.enabled', false));
    }

    /** The hours the hand-off offers, in the business's zone; its own zone when Settings name none. */
    public function booking(string $siteZone): BookingHours
    {
        $zone = $this->text('assistant.booking.timezone');

        return BookingHours::fromSettings([
            'enabled' => $this->bookingEnabled(),
            'timezone' => $zone !== '' ? $zone : $siteZone,
            'days' => $this->withDefault('assistant.booking.days'),
            'start' => $this->withDefault('assistant.booking.start'),
            'end' => $this->withDefault('assistant.booking.end'),
            'slot' => $this->withDefault('assistant.booking.slot'),
            'notice' => $this->withDefault('assistant.booking.notice'),
            'horizon' => $this->withDefault('assistant.booking.horizon'),
        ]);
    }

    /** A whole number setting, else its default. */
    private function count(string $key): int
    {
        $value = $this->withDefault($key);

        return ctype_digit($value) ? (int) $value : (int) self::DEFAULTS[$key];
    }

    private function withDefault(string $key): string
    {
        $value = $this->text($key);

        return $value !== '' ? $value : (string) (self::DEFAULTS[$key] ?? '');
    }

    private function text(string $key): string
    {
        $value = $this->config->get($key, '');

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
