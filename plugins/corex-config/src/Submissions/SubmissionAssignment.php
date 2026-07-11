<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use InvalidArgumentException;

final readonly class SubmissionAssignment
{
    public function __construct(public string $type, public string $key)
    {
        if (! in_array($this->type, ['none', 'user', 'team', 'role'], true)) {
            throw new InvalidArgumentException('The submission assignment type is invalid.');
        }

        if (($this->type === 'none') !== ($this->key === '')) {
            throw new InvalidArgumentException('The submission assignment key is invalid.');
        }
    }

    public function label(): string
    {
        return $this->type === 'none' ? 'none' : $this->type . ':' . $this->key;
    }
}
