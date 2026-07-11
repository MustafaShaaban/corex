<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

/**
 * WordPress transient-backed, actor-bound, single-use mutation previews.
 */
final class WpDataMutationPreviewStore implements DataMutationPreviewStore
{
    private const TTL = 300;

    public function issue(DataMutationRequest $request): DataMutationPreview
    {
        $token = wp_generate_password(32, false, false);
        $preview = DataMutationPreview::from($request->toArray() + [
            'token' => $token,
            'expires_at' => time() + self::TTL,
        ]);
        set_transient($this->key($token), $preview->toArray(), self::TTL);

        return $preview;
    }

    public function consume(string $token, int $actorId): ?DataMutationPreview
    {
        $key = $this->key($token);
        $payload = get_transient($key);
        delete_transient($key);
        if (! is_array($payload)) {
            return null;
        }

        $preview = DataMutationPreview::from($payload);

        return $preview->actorId === $actorId && ! $preview->expired(time()) ? $preview : null;
    }

    private function key(string $token): string
    {
        return 'corex_data_mutation_' . hash('sha256', $token);
    }
}
