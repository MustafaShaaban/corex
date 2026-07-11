<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

/**
 * Resolves comma-separated literal addresses or whitelisted context paths.
 */
final class FlowEmailAddressResolver
{
    /**
     * @param array<string,mixed> $context
     * @return list<string>
     */
    public function recipients(string $mapping, array $context): array
    {
        $recipients = [];
        foreach (array_filter(array_map('trim', explode(',', $mapping))) as $token) {
            $address = $this->address($token, $context);
            if ($address !== null) {
                $recipients[] = $address;
            }
        }

        return array_values(array_unique($recipients));
    }

    /** @param array<string,mixed> $context */
    public function replyTo(string $mapping, array $context): ?string
    {
        return $this->recipients($mapping, $context)[0] ?? null;
    }

    /** @param array<string,mixed> $context */
    private function address(string $mapping, array $context): ?string
    {
        if (filter_var($mapping, FILTER_VALIDATE_EMAIL) !== false) {
            return $mapping;
        }
        $path = str_contains($mapping, '.') ? $mapping : 'submission.' . $mapping;
        if (preg_match('/^[a-z][a-z0-9_.]*$/', $path) !== 1) {
            return null;
        }
        $value = $context;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? $value : null;
    }
}
