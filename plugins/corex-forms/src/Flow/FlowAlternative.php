<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use DomainException;

/**
 * Another way to get in touch, offered to a visitor whose form is not taking answers (spec 122, US-VIS-01).
 */
final readonly class FlowAlternative
{
    public const NONE = 'none';
    public const LINK = 'link';
    public const EMAIL = 'email';

    public function __construct(
        public string $type = self::NONE,
        public string $label = '',
        public string $url = '',
        public string $email = '',
    ) {
    }

    /** @param array<string,mixed> $stored */
    public static function fromArray(array $stored): self
    {
        $type = (string) ($stored['type'] ?? self::NONE);

        return new self(
            in_array($type, [self::LINK, self::EMAIL], true) ? $type : self::NONE,
            trim((string) ($stored['label'] ?? '')),
            trim((string) ($stored['url'] ?? '')),
            trim((string) ($stored['email'] ?? '')),
        );
    }

    /** @return array{type:string,label:string,url:string,email:string} */
    public function toArray(): array
    {
        return ['type' => $this->type, 'label' => $this->label, 'url' => $this->url, 'email' => $this->email];
    }

    /** @throws DomainException When a link is not a web address or an email is not an address. */
    public function assertValid(): void
    {
        if ($this->type === self::LINK
            && (preg_match('#^https?://#i', $this->url) !== 1 || filter_var($this->url, FILTER_VALIDATE_URL) === false)) {
            throw new DomainException(__('The alternative link must be a web address that starts with http:// or https://.', 'corex'));
        }
        if ($this->type === self::LINK && $this->label === '') {
            throw new DomainException(__('The alternative link needs a label.', 'corex'));
        }
        if ($this->type === self::EMAIL && filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException(__('The alternative email must be an email address.', 'corex'));
        }
    }
}
