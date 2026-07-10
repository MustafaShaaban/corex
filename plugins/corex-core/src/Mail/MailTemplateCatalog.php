<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

/**
 * Optional read-only catalog of templates that callers may bind by identity.
 */
interface MailTemplateCatalog
{
    /** @return list<array{id:int,slug:string,name:string}> */
    public function templates(): array;
}
