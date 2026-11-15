<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

/**
 * Resolves comma-separated literal addresses, whitelisted context paths, and `routed_owner`.
 */
final class FlowEmailAddressResolver
{
    /** The recipient "Routed owner": whoever the submission was routed to (spec 122, FR-017). */
    public const ROUTED_OWNER = 'routed_owner';

    /** Whether a mapping names the routed owner, so its delivery can carry the routing's note. */
    public static function namesRoutedOwner(string $mapping): bool
    {
        return in_array(self::ROUTED_OWNER, array_map('trim', explode(',', $mapping)), true);
    }

    /**
     * @param array<string,mixed> $context
     * @return list<string>
     */
    public function recipients(string $mapping, array $context): array
    {
        $recipients = [];
        foreach (array_filter(array_map('trim', explode(',', $mapping))) as $token) {
            if ($token === self::ROUTED_OWNER) {
                array_push($recipients, ...$this->routedOwner($context));
                continue;
            }
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

    /**
     * The addresses the routing stage worked out for the routed owner.
     *
     * @param array<string,mixed> $context
     * @return list<string>
     */
    private function routedOwner(array $context): array
    {
        $addresses = $context['routing']['recipients']['addresses'] ?? [];

        return array_values(array_filter(
            is_array($addresses) ? $addresses : [],
            static fn (mixed $address): bool => is_string($address) && filter_var($address, FILTER_VALIDATE_EMAIL) !== false,
        ));
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
