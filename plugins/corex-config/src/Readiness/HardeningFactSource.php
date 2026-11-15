<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Readiness;

defined('ABSPATH') || exit;

use Corex\Config\Security\HardeningFacts;

/**
 * Where the launch checklist reads the WordPress hardening facts (spec 118). An object rather than
 * the static call it wraps, so the one readiness evaluation receives it from the container and a
 * test can hand it facts that cannot be read.
 */
class HardeningFactSource
{
    /**
     * @return array{ssl:bool,fileEditDisabled:bool,debugDisplayOff:bool,defaultAdminAbsent:bool,indexingAllowed:bool,authSaltsConfigured:bool}
     */
    public function gather(): array
    {
        return HardeningFacts::gather();
    }
}
