<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Site;

defined('ABSPATH') || exit;

use JsonException;
use stdClass;

/**
 * The CoreX theme's tokens, as a generated client theme lists them (spec 135, FR-011 and FR-012;
 * DECISIONS #660).
 *
 * A client theme is a child of the CoreX theme. WordPress merges a child's `theme.json` into the
 * parent's key by key, except a preset list: a list the child declares replaces the parent's whole
 * list. So the child is given every preset list the parent declares, with the parent's values for
 * the client to change, and the whole of `settings.custom` and `settings.layout`. Nothing else: the
 * parent's switches (`appearanceTools`, `fluid`, `units` and the like), its `styles`, its
 * `templateParts` and its `customTemplates` stay the parent's.
 *
 * The lists are read from the CoreX theme's own file at generation, never kept in a stub, so the
 * generator is right on any line of the repository whatever its tokens. The file is looked for at
 * each candidate path in order, read only when a theme is generated, and the first one that
 * decodes to an object is used. No WordPress: whoever builds this resolves the paths.
 */
final class ParentThemeTokens
{
    /**
     * Every preset list WordPress replaces whole when a child declares it: `[settings group, list]`.
     *
     * @var list<array{0:string,1:string}>
     */
    public const PRESET_LISTS = [
        ['color', 'palette'],
        ['color', 'gradients'],
        ['color', 'duotone'],
        ['typography', 'fontFamilies'],
        ['typography', 'fontSizes'],
        ['spacing', 'spacingSizes'],
        ['shadow', 'presets'],
    ];

    /** The settings a child takes whole. */
    private const WHOLE_SETTINGS = ['custom', 'layout'];

    /**
     * @param list<string> $candidates Where the CoreX theme's `theme.json` may be, in the order to try.
     */
    public function __construct(private readonly array $candidates)
    {
    }

    /**
     * The child theme's `theme.json`, encoded as it is written.
     *
     * @throws ParentThemeUnavailable When no candidate can be read and decoded.
     */
    public function childThemeJson(): string
    {
        return self::encode(self::childOf($this->parent()));
    }

    /**
     * The part of a parent `theme.json` a child lists, in the parent's order.
     */
    public static function childOf(stdClass $parent): stdClass
    {
        $child = new stdClass();

        if (isset($parent->{'$schema'})) {
            $child->{'$schema'} = $parent->{'$schema'};
        }
        if (isset($parent->version)) {
            $child->version = $parent->version;
        }

        $settings = $parent->settings ?? null;
        $taken    = new stdClass();

        if ($settings instanceof stdClass) {
            foreach (get_object_vars($settings) as $key => $value) {
                $kept = self::settingFor((string) $key, $value);

                if ($kept !== null) {
                    $taken->{$key} = $kept;
                }
            }
        }

        $child->settings = $taken;

        return $child;
    }

    /**
     * Pretty-printed with tabs, slashes as written, ending in a newline.
     */
    public static function encode(stdClass $json): string
    {
        $encoded = (string) json_encode(
            $json,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );

        // json_encode indents with four spaces. A JSON string holds no raw line break, so every
        // run of leading spaces is indentation.
        $tabbed = (string) preg_replace_callback(
            '/^(?: {4})+/m',
            static fn (array $indent): string => str_repeat("\t", intdiv(strlen($indent[0]), 4)),
            $encoded,
        );

        return $tabbed . "\n";
    }

    /**
     * What the child keeps of one setting of the parent's, or null for none of it.
     */
    private static function settingFor(string $key, mixed $value): mixed
    {
        if (in_array($key, self::WHOLE_SETTINGS, true)) {
            return $value;
        }

        if (! $value instanceof stdClass) {
            return null;
        }

        $lists = new stdClass();

        foreach (self::PRESET_LISTS as [$group, $list]) {
            if ($group === $key && property_exists($value, $list)) {
                $lists->{$list} = $value->{$list};
            }
        }

        return get_object_vars($lists) === [] ? null : $lists;
    }

    /**
     * @throws ParentThemeUnavailable
     */
    private function parent(): stdClass
    {
        foreach ($this->candidates as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }

            try {
                $decoded = json_decode((string) file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            if ($decoded instanceof stdClass) {
                return $decoded;
            }
        }

        throw new ParentThemeUnavailable($this->candidates);
    }
}
