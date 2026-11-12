<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use RuntimeException;

/**
 * Another save of the login settings held them past the wait ({@see LoginSettingsLock}), and nothing
 * was written. Trying again a moment later is the answer.
 */
final class LoginSettingsBusy extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The login settings are being saved by another request. Nothing was written.');
    }
}
