<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Account;

defined('ABSPATH') || exit;

/**
 * A typed, result-bearing outcome for every account operation (constitution: no UI
 * infers success from a void call). Carries a machine `code`, a translated message,
 * and — on success — the affected user id and any safe payload. Never contains a
 * password, key, or session token.
 */
final class AccountResult
{
    /**
     * @param array<string, scalar|null> $data
     */
    private function __construct(
        public readonly bool $success,
        public readonly string $code,
        public readonly string $message,
        public readonly ?int $userId = null,
        public readonly array $data = [],
    ) {
    }

    /**
     * @param array<string, scalar|null> $data
     */
    public static function ok(string $code, string $message, ?int $userId = null, array $data = []): self
    {
        return new self(true, $code, $message, $userId, $data);
    }

    /**
     * @param array<string, scalar|null> $data
     */
    public static function fail(string $code, string $message, array $data = []): self
    {
        return new self(false, $code, $message, null, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'code'    => $this->code,
            'message' => $this->message,
            'userId'  => $this->userId,
            'data'    => $this->data,
        ];
    }
}
