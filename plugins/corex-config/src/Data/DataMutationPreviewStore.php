<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

interface DataMutationPreviewStore
{
    public function issue(DataMutationRequest $request): DataMutationPreview;

    public function consume(string $token, int $actorId): ?DataMutationPreview;
}
