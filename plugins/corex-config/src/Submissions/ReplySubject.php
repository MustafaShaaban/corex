<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Mail\SubjectLine;

/**
 * The subject a reply starts with, for whoever writes it to change (spec 106, FR-035).
 *
 * From the form the submission came from, which is what the person who wrote in knows they
 * filled in. Its name is, in this order: what a form built in the admin was called when the
 * submission was sent; what the site's forms call it now, which is where a form defined in
 * code says its own name; its slug as a person would read it.
 *
 * "Re:" is written as it is and not translated. It is what mail programs look for to keep a
 * conversation together, in every language, and a translated one would be put in front of
 * itself the next time.
 */
final class ReplySubject
{
    /** A subject is a line. A name longer than this is cut: a form can be called anything. */
    private const LONGEST_NAME = 200;

    /**
     * @param array<string,mixed> $record  A submission, as the inbox reads it.
     * @param string              $knownAs What the site's forms call this form now, or ''.
     */
    public static function startingFor(array $record, string $knownAs = ''): string
    {
        $slug = self::line((string) ($record['form'] ?? ''));
        $name = self::line((string) ($record['flow'] ?? ''));
        if ($name === '' || $name === $slug) {
            $name = self::line($knownAs);
        }
        if ($name === '' || $name === $slug) {
            $name = ucwords(str_replace(['-', '_'], ' ', $slug));
        }
        if ($name === '') {
            $name = __('your message', 'corex');
        }
        // A form whose own name answers already is not answered twice.
        if (preg_match('/^re\s*:/i', $name) === 1) {
            return $name;
        }

        return 'Re: ' . $name;
    }

    /**
     * The subject of a reply to an email: its own, answered once. '' where it had none.
     */
    public static function answering(string $subject): string
    {
        $subject = self::line($subject);
        if ($subject === '' || preg_match('/^re\s*:/i', $subject) === 1) {
            return $subject;
        }

        return 'Re: ' . $subject;
    }

    /**
     * A name as one line of plain text. WordPress keeps a title with its `&` and `<` written
     * as entities, and a subject is not markup: it would reach a customer as "&amp;". A
     * subject is a header, and holds no line break.
     */
    private static function line(string $name): string
    {
        $line = SubjectLine::of(html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return mb_substr($line, 0, self::LONGEST_NAME);
    }
}
