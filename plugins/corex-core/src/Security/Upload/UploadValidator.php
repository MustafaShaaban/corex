<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security\Upload;

defined('ABSPATH') || exit;

/**
 * Validates an uploaded file descriptor before it is stored (spec FR-001, FR-002):
 * no PHP upload error, non-empty, within the size cap, an allowed MIME type, and an
 * extension that matches that type. Pure — it operates on the descriptor only, never
 * a caller-supplied path (path-traversal safe). The boundary store (wp_handle_upload)
 * re-checks the real MIME as defense-in-depth.
 */
final class UploadValidator
{
    /**
     * @param array<string,list<string>> $allowedMimes mime => allowed extensions
     */
    public function __construct(
        private readonly array $allowedMimes,
        private readonly int $maxBytes,
    ) {
    }

    /**
     * @param array{name?:string,type?:string,size?:int,tmp_name?:string,error?:int} $file
     */
    public function validate(array $file): UploadResult
    {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return new UploadResult(false, 'upload_error');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            return new UploadResult(false, 'empty');
        }

        if ($size > $this->maxBytes) {
            return new UploadResult(false, 'too_large');
        }

        $mime = (string) ($file['type'] ?? '');
        if (! isset($this->allowedMimes[$mime])) {
            return new UploadResult(false, 'type_not_allowed');
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (! in_array($extension, $this->allowedMimes[$mime], true)) {
            return new UploadResult(false, 'extension_mismatch');
        }

        return new UploadResult(true);
    }
}
