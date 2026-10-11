<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Generators;

defined('ABSPATH') || exit;

/**
 * Scaffolds a site's own email design: a working copy of CoreX's standard one, in the site's
 * own code, for its developer to change (spec 106, FR-019).
 *
 * A copy and not a subclass. The standard design is final, and a site's template is the one
 * thing about its emails that should not move when CoreX is updated.
 */
final class EmailDesignGenerator extends Generator
{
    public function stub(): string
    {
        return 'email-design';
    }

    public function suffix(): string
    {
        return 'EmailDesign';
    }

    public function subPath(): string
    {
        return 'Mail';
    }

    /**
     * @return array<string,string>
     */
    public function placeholders(string $className, GeneratorContext $context): array
    {
        return [
            'class'     => $className,
            'namespace' => $context->namespace . '\\Mail',
        ];
    }
}
