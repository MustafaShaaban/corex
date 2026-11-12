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
    /**
     * @return list<array{id:int,slug:string,name:string,variables?:list<string>}> `variables`:
     *         the keys the template's active version uses (spec 120), where the catalog knows them.
     */
    public function templates(): array;
}
