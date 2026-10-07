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
 *
 * Some hosts do not let PHP write to uploads at all: a read-only filesystem, or a web server
 * running as a user that does not own the directory. There an export is written to the system's
 * temporary directory instead, which is not served either. It is saved by the browser as soon as
 * it is ready; what such a host gives up is downloading it again later, once the system has
 * cleared its temporary files.
 */
final class ProtectedExportDirectory implements ExportDirectory
{
    private const SUBDIRECTORY = 'exports';
    private const TEMPORARY = 'corex-exports';

    public function path(): string
    {
        $protected = ProtectedUploads::ensure();
        if ($protected !== '' && wp_mkdir_p($protected . '/' . self::SUBDIRECTORY)) {
            return $protected . '/' . self::SUBDIRECTORY;
        }

        $temporary = untrailingslashit(get_temp_dir()) . '/' . self::TEMPORARY;
        if (wp_mkdir_p($temporary)) {
            return $temporary;
        }

        throw new RuntimeException(__('CoreX could not create the directory exports are written to.', 'corex'));
    }
}
