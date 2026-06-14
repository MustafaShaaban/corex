<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Assets;

defined('ABSPATH') || exit;

/**
 * A build manifest (spec 047): a map of source name → hashed output file (+ content hash).
 * Pure + immutable — constructed once from the decoded manifest (read + cached at the
 * boundary). A malformed/absent manifest is simply empty (graceful fallback, never a fatal).
 */
final class BuildManifest
{
    /**
     * @param array<string,mixed> $entries
     */
    private function __construct(private readonly array $entries)
    {
    }

    /**
     * @param mixed $data the decoded manifest (array), or anything else → empty
     */
    public static function fromArray(mixed $data): self
    {
        return new self(is_array($data) ? $data : []);
    }

    /**
     * @return array{file:string,hash:string}|null the hashed file + hash, or null if absent
     */
    public function lookup(string $source): ?array
    {
        $entry = $this->entries[$source] ?? null;

        if (is_array($entry) && isset($entry['file'])) {
            return ['file' => (string) $entry['file'], 'hash' => (string) ($entry['hash'] ?? '')];
        }

        if (is_string($entry) && $entry !== '') {
            return ['file' => $entry, 'hash' => ''];
        }

        return null;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }
}
