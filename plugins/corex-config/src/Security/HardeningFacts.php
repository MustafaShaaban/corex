<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security;

defined('ABSPATH') || exit;

/**
 * Gathers the REAL, locally-verified WordPress hardening facts (spec 063/064). This is the single
 * boundary that reads the constants/functions, shared by the Operations & Security screen and the
 * Overview readiness/integration panels so the same truthful signal is never computed two different
 * ways (DRY). No network, no writes — only constant/function reads.
 */
final class HardeningFacts
{
    /**
     * @return array{ssl:bool,fileEditDisabled:bool,debugDisplayOff:bool,defaultAdminAbsent:bool}
     */
    public static function gather(): array
    {
        // WordPress displays PHP errors to the page when WP_DEBUG is on AND WP_DEBUG_DISPLAY is not
        // explicitly false (its default when undefined is to display). Mirror that exactly.
        $debugOn        = defined('WP_DEBUG') && WP_DEBUG === true;
        $displaySet     = defined('WP_DEBUG_DISPLAY');
        $displayEnabled = $debugOn && (! $displaySet || WP_DEBUG_DISPLAY !== false);

        return [
            'ssl'                => is_ssl()
                || (function_exists('force_ssl_admin') && force_ssl_admin())
                || str_starts_with((string) home_url(), 'https://'),
            'fileEditDisabled'   => defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT === true,
            'debugDisplayOff'    => ! $displayEnabled,
            'defaultAdminAbsent' => username_exists('admin') === null,
        ];
    }
}
