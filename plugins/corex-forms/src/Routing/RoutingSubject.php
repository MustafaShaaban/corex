<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\Flow;

/**
 * What a routing target is resolved for: the flow, the answers, the page the form was sent from
 * (null when it is not known) and whether this is a marked test.
 */
final readonly class RoutingSubject
{
    /** @param array<string,mixed> $values */
    public function __construct(
        public Flow $flow,
        public array $values,
        public ?int $pageId = null,
        public bool $isTest = false,
    ) {
    }

    /** @return array<string,mixed> What a routing extension is handed. */
    public function toArray(): array
    {
        return [
            'values' => $this->values,
            'flow' => ['id' => $this->flow->id, 'slug' => $this->flow->slug, 'owner_id' => $this->flow->ownerId],
            'page_id' => $this->pageId,
            'is_test' => $this->isTest,
        ];
    }
}
