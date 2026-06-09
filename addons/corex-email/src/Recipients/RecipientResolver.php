<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Recipients;

defined('ABSPATH') || exit;

use Corex\Email\Template\MailContext;

/**
 * Resolves recipient specs — `fixed` (a literal address), `role` (everyone in a
 * WordPress role, via the injected directory), or `dynamic` (a whitelisted context
 * path) — into a deduplicated, validated address set (spec FR-007, FR-008). Pure:
 * the only WordPress access is behind the injected UserDirectory. Invalid or empty
 * results are dropped.
 */
final class RecipientResolver
{
    public function __construct(private readonly UserDirectory $users)
    {
    }

    /**
     * @param list<array{type:string,value:string}> $specs
     *
     * @return array{valid:list<string>,dropped:list<string>}
     */
    public function resolve(array $specs, MailContext $context): array
    {
        $addresses = [];

        foreach ($specs as $spec) {
            $addresses = array_merge($addresses, match ($spec['type']) {
                'fixed'   => [$spec['value']],
                'role'    => $this->users->emailsInRole($spec['value']),
                'dynamic' => [$context->get($spec['value'])],
                default   => [],
            });
        }

        $valid   = [];
        $dropped = [];

        foreach (array_unique($addresses) as $address) {
            if ($address === '') {
                continue;
            }

            if (filter_var($address, FILTER_VALIDATE_EMAIL) !== false) {
                $valid[] = $address;
            } else {
                $dropped[] = $address;
            }
        }

        return ['valid' => array_values($valid), 'dropped' => array_values($dropped)];
    }
}
