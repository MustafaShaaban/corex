<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;

/**
 * What approving one pending request would grant, shown to the approver before they confirm
 * (spec 112, FR-002 and FR-003).
 */
final class AccessApprovalPreview
{
    /**
     * @param list<CorexAbility>                       $abilities What the request grants, area expanded.
     * @param list<array{code:string,ability:string}> $blockers  What the access policy refuses.
     * @param string|null                              $typedName The name the approver must type, when
     *                                                            something granted is dangerous.
     * @param string                                   $targetHash What the confirmation is bound to.
     */
    public function __construct(
        public readonly int $requestId,
        public readonly int $requesterId,
        public readonly string $reason,
        public readonly ?string $areaKey,
        public readonly array $abilities,
        public readonly array $blockers,
        public readonly ?string $typedName,
        public readonly string $targetHash,
    ) {
    }
}
