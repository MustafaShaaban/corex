<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

/**
 * What happened when an operator acted on the preview link (spec 101).
 *
 * `token` is set only when a link was just made, and this object is the only place it ever is: it
 * travels from {@see PreviewLinkService} to the response that shows it, once, and is stored nowhere.
 */
final readonly class PreviewLinkResult
{
    public const CREATED     = 'created';
    public const REGENERATED = 'regenerated';
    public const REVOKED     = 'revoked';

    /** Asked to create a link where one already exists. Nothing was replaced. */
    public const EXISTS = 'exists';

    /** Asked to regenerate or revoke a link where there is none. */
    public const NONE = 'none';

    /** The site is not in Coming soon, and a preview link exists only while it is. */
    public const NOT_COMING_SOON = 'not_coming_soon';

    public const INVALID = 'invalid';

    public function __construct(
        public string $status,
        public ?string $token = null,
    ) {
    }
}
