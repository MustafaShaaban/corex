<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant;

defined('ABSPATH') || exit;

use Corex\Assistant\Chain\Provider;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\Config\Truthy;

/**
 * The assistant's switch, name, mark, cap, and a key and a model per service (spec 129, FR-006).
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
    public const DAILY_CAP = 'assistant.daily_cap';

    /** Questions a day across the whole site, unless the Settings say otherwise. */
    public const DEFAULT_DAILY_CAP = 600;

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
        $cap = $this->text(self::DAILY_CAP);

        return ctype_digit($cap) ? (int) $cap : self::DEFAULT_DAILY_CAP;
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

    /** Whether anything can answer: on, and at least one service keyed. Read on every public page. */
    public function live(): bool
    {
        if (! $this->enabled()) {
            return false;
        }
        foreach (array_keys(Provider::CATALOGUE) as $id) {
            if ($this->key($id) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * The services that have a key, in the chain's order.
     *
     * @return list<Provider>
     */
    public function chain(): array
    {
        $chain = [];
        foreach (Provider::CATALOGUE as $id => $entry) {
            $key = $this->key($id);
            if ($key !== '') {
                $chain[] = new Provider($id, $entry['label'], $entry['base'], $this->model($id), $key, $entry['extra']);
            }
        }

        return $chain;
    }

    private function text(string $key): string
    {
        $value = $this->config->get($key, '');

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
