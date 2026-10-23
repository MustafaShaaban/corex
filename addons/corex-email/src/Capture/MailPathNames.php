<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Capture;

defined('ABSPATH') || exit;

/**
 * The name the Email Studio log gives an email sent through CoreX's mailer, from its template,
 * when nothing that sent it gave a name (spec 113, research R4).
 */
final class MailPathNames
{
    public static function forTemplate(?string $template): string
    {
        return match (true) {
            $template === null || $template === '' => __('CoreX mail', 'corex'),
            $template === 'newsletter-confirm'      => __('Newsletter: confirmation', 'corex'),
            $template === 'newsletter-notify'       => __('Newsletter: new post', 'corex'),
            str_starts_with($template, 'call-request-') => __('Bookings: call request', 'corex'),
            str_starts_with($template, 'careers-')  => __('Careers: application', 'corex'),
            $template === 'contact-notification',
            $template === 'form-notification'       => __('Form notification', 'corex'),
            default                                 => sprintf(
                /* translators: %s: the email template's name. */
                __('Site code: %s', 'corex'),
                $template,
            ),
        };
    }
}
