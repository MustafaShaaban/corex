<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use DomainException;
use Exception;

/**
 * A stored flow or flow version that the store holds and nothing can make a record of.
 *
 * It is about what is stored, never about the code reading it: a payload that did not arrive, a
 * date that cannot be parsed, a pointer at a draft that was never written. A TypeError is not one
 * of these and is not turned into one.
 */
final class UnreadableFlowRecord extends DomainException
{
    private function __construct(
        public readonly int $recordId,
        public readonly string $why,
        ?Exception $previous = null,
    ) {
        parent::__construct(
            /* translators: %d: the id of the stored record. */
            sprintf(__('Stored flow record %d could not be read.', 'corex'), $recordId),
            0,
            $previous,
        );
    }

    public static function because(int $recordId, Exception $reason): self
    {
        return new self($recordId, sprintf('%s: %s', $reason::class, $reason->getMessage()), $reason);
    }

    public static function missingDraft(int $recordId, int $versionNumber): self
    {
        return new self($recordId, sprintf('it points at draft version %d, which is not stored', $versionNumber));
    }

    /** The line for the log: which record, and what was wrong with it. */
    public function report(): string
    {
        return sprintf('Stored flow record %d could not be read and was left out (%s).', $this->recordId, $this->why);
    }
}
