<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Routing;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Trigger-to-template route with declarative recipient and reply-to rules.
 */
final class EmailRoute
{
    /**
     * @param list<array{source:string,path?:string,value?:string}> $recipientRules
     * @param array{source:string,path?:string,value?:string}|null  $replyToRule
     */
    public function __construct(
        public readonly int $id,
        public readonly string $trigger,
        public readonly int $templateId,
        public readonly array $recipientRules,
        public readonly ?array $replyToRule,
        public readonly bool $enabled,
        public readonly int $updatedBy,
        public readonly DateTimeImmutable $updatedAt,
        public readonly ?int $flowId = null,
        public readonly string $templateVersionPolicy = 'active',
        public readonly int $priority = 100,
    ) {
        if ($this->id < 0 || $this->templateId < 1 || $this->updatedBy < 1) {
            throw new InvalidArgumentException(__('Email route identifiers are invalid.', 'corex'));
        }

        if (preg_match('/^[a-z][a-z0-9_.-]*$/', $this->trigger) !== 1 || $this->recipientRules === []) {
            throw new InvalidArgumentException(__('Email route trigger and recipients are required.', 'corex'));
        }

        if (($this->flowId !== null && $this->flowId < 1)
            || ! in_array($this->templateVersionPolicy, ['active'], true)
            || $this->priority < 0
        ) {
            throw new InvalidArgumentException(__('Email route policy metadata is invalid.', 'corex'));
        }

        foreach ([...$this->recipientRules, ...($this->replyToRule === null ? [] : [$this->replyToRule])] as $rule) {
            $source = $rule['source'] ?? '';
            if (! in_array($source, ['context', 'literal'], true)) {
                throw new InvalidArgumentException(__('Email route rule source is invalid.', 'corex'));
            }

            if ($source === 'context' && preg_match('/^[a-z][a-z0-9_.]*$/', (string) ($rule['path'] ?? '')) !== 1) {
                throw new InvalidArgumentException(__('Email route context path is invalid.', 'corex'));
            }

            if ($source === 'literal' && filter_var($rule['value'] ?? '', FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException(__('Email route literal recipient is invalid.', 'corex'));
            }
        }
    }
}
