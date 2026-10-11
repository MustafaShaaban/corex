<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * What will happen to a reply sent from this site now, said before one is written (spec 106).
 *
 * It is the answer of the decision the sending itself makes, carried across the seam to the
 * Submissions inbox, which knows nothing of providers or environments. The reason is written
 * for the person at the reply box and is empty when a reply is simply delivered.
 */
final readonly class ReplyReadiness
{
    /** A reply is handed to the site's mail provider. */
    public const DELIVER = 'deliver';

    /** A reply is kept on the site and reaches nobody: a development or staging site. */
    public const CAPTURE = 'capture';

    /** A reply would be refused: no provider is chosen, or live delivery is off. */
    public const REFUSE = 'refuse';

    /** Nothing on this site can send a reply: the email add-on is not active. */
    public const UNAVAILABLE = 'unavailable';

    private const MODES = [self::DELIVER, self::CAPTURE, self::REFUSE, self::UNAVAILABLE];

    public function __construct(
        public string $mode,
        public string $code,
        public string $reason,
    ) {
        if (! in_array($this->mode, self::MODES, true)) {
            throw new InvalidArgumentException('Reply readiness mode is invalid.');
        }
    }

    /** Whether sending a reply now does anything but record a refusal. */
    public function sendable(): bool
    {
        return in_array($this->mode, [self::DELIVER, self::CAPTURE], true);
    }

    /** @return array{mode:string,code:string,reason:string} */
    public function toArray(): array
    {
        return ['mode' => $this->mode, 'code' => $this->code, 'reason' => $this->reason];
    }
}
