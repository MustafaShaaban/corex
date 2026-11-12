<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * Why one field of a login settings save was refused (spec 124, FR-015, User Story 7 scenario 3).
 *
 * A reason code and the facts a sentence needs (the range, the proxy line), never the sentence:
 * the layer that answers a person translates it, as it does for {@see LoginSlug::rejectionReason()}.
 */
final readonly class LoginSettingsProblem
{
    /** A number outside its range, or not a whole number: `min` and `max` say the range. */
    public const REASON_OUT_OF_RANGE = 'out_of_range';

    /** A switch that is neither on nor off. */
    public const REASON_NOT_ON_OR_OFF = 'not_on_or_off';

    /** A trusted proxy line that is not an IPv4 or IPv6 address or range: `line` and `value` name it. */
    public const REASON_NOT_A_PROXY = 'not_a_proxy';

    /**
     * A trusted proxy range that covers every address (`/0`): `line` and `value` name it. It would let
     * any visitor choose the address their sign-in attempts are counted by.
     */
    public const REASON_TRUSTS_EVERY_ADDRESS = 'trusts_every_address';

    /** More trusted proxy lines than are kept: `max` says how many. */
    public const REASON_TOO_MANY_PROXIES = 'too_many_proxies';

    /** Trusted proxies sent as something other than text or a list of text. */
    public const REASON_NOT_A_LIST = 'not_a_list';

    /** A length sent in minutes and in seconds in one request. */
    public const REASON_TWO_UNITS = 'two_units';

    /** The three switches and the previous screen's `enabled`/`block_default_endpoints` in one request. */
    public const REASON_TWO_SHAPES = 'two_shapes';

    /** An unusable login address: `detail` is a LoginSlug or LoginSlugAvailability reason. */
    public const REASON_ADDRESS = 'address';

    /** An unusable login address while `wp-login.php` is to be hidden: `detail` as above. */
    public const REASON_ADDRESS_NEEDED_TO_HIDE = 'address_needed_to_hide';

    /**
     * @param string             $field  the request field the problem is about
     * @param array<int,string>  $lines  every refused proxy line, by its line number
     */
    public function __construct(
        public string $field,
        public string $reason,
        public ?int $min = null,
        public ?int $max = null,
        public ?int $line = null,
        public string $value = '',
        public string $detail = '',
        public array $lines = [],
    ) {
    }

    /**
     * The store's refusal (hiding `wp-login.php` with no usable address), as the same problem the
     * request's own check gives.
     */
    public static function fromRefusal(LoginSettingsRefused $refused): self
    {
        return new self($refused->field, self::REASON_ADDRESS_NEEDED_TO_HIDE, detail: $refused->reason);
    }

    /**
     * The facts beside the sentence, for a screen that marks the field.
     *
     * @return array<string,mixed>
     */
    public function toDetails(): array
    {
        $details = ['reason' => $this->reason];

        if ($this->min !== null) {
            $details['min'] = $this->min;
        }

        if ($this->max !== null) {
            $details['max'] = $this->max;
        }

        if ($this->detail !== '') {
            $details['detail'] = $this->detail;
        }

        if ($this->lines !== []) {
            $details['lines'] = array_map(
                static fn (int $line, string $value): array => ['line' => $line, 'value' => $value],
                array_keys($this->lines),
                array_values($this->lines),
            );
        }

        return $details;
    }
}
