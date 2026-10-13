<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Forms\Schema\FieldSchema;

/**
 * What a request answers a file question with: the file it carried for it, or nothing.
 *
 * A request has two channels. Its body is what the visitor typed, and its file parts are what PHP
 * received as uploads. A file question is answered by the second and never by the first: what the
 * body says under the question's name was kept as the answer when no file part arrived, and an
 * answer to a file question is read everywhere after this as the number of a stored file.
 *
 * Both ways a submission is taken ask here: a form defined in code, with the file parts its route
 * read, and a flow built in the admin, whose route reads none.
 */
final class FileFieldAnswers
{
    private const FILE = 'file';

    /**
     * The answers, with each file question holding the file part sent for it or left out.
     *
     * @param array<string,FieldSchema>                                                            $schema
     * @param array<string,mixed>                                                                  $input
     * @param array<string,array{name?:string,type?:string,size?:int,tmp_name?:string,error?:int}> $files
     *
     * @return array<string,mixed>
     */
    public static function from(array $schema, array $input, array $files = []): array
    {
        foreach ($schema as $name => $field) {
            if ($field->type !== self::FILE) {
                continue;
            }

            unset($input[$name]);

            // Absent stays absent rather than becoming an empty descriptor: `required` must be able
            // to tell "no file was sent" from "a file was sent and it failed".
            if (isset($files[$name]) && ! self::carriesNoFile($files[$name])) {
                $input[$name] = $files[$name];
            }
        }

        return $input;
    }

    /**
     * Whether a file part arrived with no file in it.
     *
     * A browser posting a form as multipart sends a part for every file input, chosen or not. An
     * empty one is the visitor leaving the field empty, which is `required`'s to judge. Passed on as
     * an upload it got past `required`, because a descriptor is not an empty value, and then failed
     * to be stored.
     *
     * @param array{name?:string,type?:string,size?:int,tmp_name?:string,error?:int} $file
     */
    private static function carriesNoFile(array $file): bool
    {
        return (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE;
    }
}
