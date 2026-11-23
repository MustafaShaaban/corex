<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks\Languages;

defined('ABSPATH') || exit;

/**
 * Where the language switcher reads the site's languages (spec 137, FR-029; DECISIONS #694).
 *
 * Bound in the container by `BlocksServiceProvider`. CoreX ships {@see WordPressLanguageSource},
 * which reads WordPress alone. A site, or a later spec's Polylang or WPML source, replaces the
 * binding once CoreX is up, and the block is not touched:
 *
 *     Corex::onReady( static function ( $app ): void {
 *         $app->container()->singleton( LanguageSource::class, MySiteLanguages::class );
 *     } );
 */
interface LanguageSource
{
    /**
     * The site's languages for the current request, in the order a switcher lists them. A source
     * marks one of them current: the language of the page being shown.
     *
     * @return list<Language>
     */
    public function languages(): array;
}
