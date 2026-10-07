<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Export;

defined('ABSPATH') || exit;

use Corex\Security\Upload\ProtectedUploads;
use RuntimeException;

/**
 * Exports hold personal data, so they are written where uploaded personal data is: the protected
 * uploads directory, which a web server is told not to serve. A file leaves it only through a
 * route that checks who is asking.
 */
final class ProtectedExportDirectory implements ExportDirectory
{
    private const SUBDIRECTORY = 'exports';

    public function path(): string
    {
        $protected = ProtectedUploads::ensure();
        $path      = $protected . '/' . self::SUBDIRECTORY;

        if ($protected === '' || ! wp_mkdir_p($path)) {
            throw new RuntimeException(__('CoreX could not create the directory exports are written to.', 'corex'));
        }

        return $path;
    }
}
