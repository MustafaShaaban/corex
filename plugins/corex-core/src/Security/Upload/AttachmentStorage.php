<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security\Upload;

defined('ABSPATH') || exit;

/**
 * Somewhere an uploaded file can be put and later referred to (spec 081).
 *
 * An interface rather than the concrete {@see AttachmentStore} because storing a file is a
 * filesystem-and-WordPress boundary, and the services that use it — `ApplicationService`, the forms
 * submission pipeline — have logic worth testing that has nothing to do with where bytes land.
 * This is the same seam `Mailer` and `ApplicationStore` already draw in this codebase, for the same
 * reason.
 */
interface AttachmentStorage
{
    /**
     * @param array{name?:string,type?:string,size?:int,tmp_name?:string,error?:int} $file A `$_FILES` entry.
     * @param string                                                                 $context Short label recorded on the
     *                                                                                        attachment, so an operator can
     *                                                                                        tell where a file came from.
     */
    public function store(array $file, string $context = ''): AttachmentResult;

    /**
     * Remove a stored file. Implementations MUST refuse ids they did not create.
     */
    public function forget(int $attachmentId): bool;
}
