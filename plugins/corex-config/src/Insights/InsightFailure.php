<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Insights;

defined('ABSPATH') || exit;

/**
 * Recognises a failed run stored before spec 114 (US-SAFE-12, FR-012).
 *
 * Until then a PageSpeed run that could not complete was stored as a completed one with a
 * score of 50, no measures, and the reason as its summary. Such a row is read as the failure it
 * was, with the reason it carries; the stored option is never rewritten, so no row is lost.
 */
final class InsightFailure
{
    /** The kind a recognised old row is given: what went wrong was not stored, only the words. */
    public const LEGACY = 'legacy';

    /** The score every old failed row was stored with. */
    private const LEGACY_SCORE = 50;

    /**
     * The sentences an earlier release stored as the summary of a run that could not complete,
     * in English. The HTTP one is a format: any status number matches.
     */
    public const LEGACY_MESSAGES = [
        'PageSpeed cannot crawl a local or private URL. Test a public URL instead.',
        'PageSpeed quota was exceeded. Add an API key, or wait and try again.',
        'The PageSpeed API key was rejected. Check the key in Settings.',
        'PageSpeed returned an unreadable response. Try again shortly.',
        'PageSpeed returned HTTP %d. Try again shortly.',
        'The performance result could not be read.',
    ];

    /**
     * @param list<string> $translated the same sentences in the site's language, when it has one
     */
    public function __construct(private readonly array $translated = [])
    {
    }

    /**
     * The recogniser for this site: the English sentences and their translation in its language.
     * Each is written out so that a translation tool finds it.
     */
    public static function inSiteLanguage(): self
    {
        return new self([
            __('PageSpeed cannot crawl a local or private URL. Test a public URL instead.', 'corex'),
            __('PageSpeed quota was exceeded. Add an API key, or wait and try again.', 'corex'),
            __('The PageSpeed API key was rejected. Check the key in Settings.', 'corex'),
            __('PageSpeed returned an unreadable response. Try again shortly.', 'corex'),
            /* translators: %d: HTTP status code */
            __('PageSpeed returned HTTP %d. Try again shortly.', 'corex'),
            __('The performance result could not be read.', 'corex'),
        ]);
    }

    /**
     * The row as it should be read: unchanged, unless it is an old failed run.
     *
     * @param array<string,mixed> $row
     *
     * @return array<string,mixed>
     */
    public function read(array $row): array
    {
        if (! $this->isLegacyFailure($row)) {
            return $row;
        }

        return InsightResult::failed(
            (string) ($row['provider'] ?? ''),
            (string) ($row['label'] ?? ''),
            self::LEGACY,
            (string) $row['summary'],
            (int) ($row['checkedAt'] ?? 0),
        )->toArray();
    }

    /** @param array<string,mixed> $row */
    private function isLegacyFailure(array $row): bool
    {
        if (($row['status'] ?? '') === InsightResult::STATUS_FAILED
            || ($row['score'] ?? null) !== self::LEGACY_SCORE
            || ($row['metrics'] ?? []) !== []
            || ! is_string($row['summary'] ?? null)) {
            return false;
        }

        foreach ([...self::LEGACY_MESSAGES, ...$this->translated] as $message) {
            if ($this->matches($message, $row['summary'])) {
                return true;
            }
        }

        return false;
    }

    private function matches(string $message, string $summary): bool
    {
        if (! str_contains($message, '%d')) {
            return $message === $summary;
        }

        $pattern = '/^' . str_replace('%d', '\d+', preg_quote($message, '/')) . '$/u';

        return preg_match($pattern, $summary) === 1;
    }
}
