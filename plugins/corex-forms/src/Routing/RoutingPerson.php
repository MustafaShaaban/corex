<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

/**
 * A person routing can reach: their account, their name and their address ('' when they have none).
 */
final readonly class RoutingPerson
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {
    }

    /** @return array{id:int,name:string,email:string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email];
    }
}
