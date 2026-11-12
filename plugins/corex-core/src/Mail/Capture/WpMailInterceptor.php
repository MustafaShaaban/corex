<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail\Capture;

defined('ABSPATH') || exit;

/**
 * Asks the gate about every `wp_mail()` call: CoreX's own last resorts, WordPress core and other
 * plugins (spec 113, research R3). Answering `true` tells WordPress the email was handled and
 * nothing is sent; `false` makes `wp_mail()` report a failure; null lets it send.
 */
final class WpMailInterceptor
{
    public const PRIORITY = 999;

    public function __construct(
        private readonly MailGate $gate,
        private readonly MailPath $paths,
    ) {
    }

    public function register(): void
    {
        add_filter('pre_wp_mail', [$this, 'intercept'], self::PRIORITY, 2);
    }

    /**
     * @param null|bool           $return what an earlier filter answered
     * @param array<string,mixed> $atts   `to`, `subject`, `message`, `headers`, `attachments`
     */
    public function intercept(mixed $return, array $atts): mixed
    {
        // A name left by WordPress is for this email only, whatever happens to it.
        $wordpressName = $this->paths->takeNext();
        if ($return !== null) {
            return $return;
        }

        $corexName = $this->paths->current();
        $decision = $this->gate->decide($corexName !== null);
        if ($decision === MailDecision::Deliver) {
            return null;
        }

        $message = $this->message($atts, $corexName ?? $wordpressName ?? __('Other: wp_mail', 'corex'));
        if ($decision === MailDecision::Refuse) {
            $this->gate->refuse($message);

            return false;
        }

        return $this->gate->keep($message, $decision) !== null;
    }

    /** @param array<string,mixed> $atts */
    private function message(array $atts, string $path): CapturedMessage
    {
        $headers = $this->headers($atts['headers'] ?? []);

        return new CapturedMessage(
            to: $this->addresses($atts['to'] ?? []),
            subject: (string) ($atts['subject'] ?? ''),
            body: (string) ($atts['message'] ?? ''),
            path: $path,
            cc: $this->addresses($headers['cc'] ?? ''),
            bcc: $this->addresses($headers['bcc'] ?? ''),
            replyTo: isset($headers['reply-to']) ? $headers['reply-to'] : null,
            headers: $headers,
            attachments: array_values(array_filter(array_map('strval', (array) ($atts['attachments'] ?? [])))),
        );
    }

    /** @return list<string> */
    private function addresses(mixed $value): array
    {
        $list = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(static fn ($address): string => trim((string) $address), $list)));
    }

    /**
     * `wp_mail()` takes headers as one string or a list of lines; the same names are joined, as
     * WordPress joins several Cc lines.
     *
     * @return array<string,string> lower-case name => value
     */
    private function headers(mixed $headers): array
    {
        $lines = is_array($headers) ? $headers : preg_split('/\r\n|\r|\n/', (string) $headers);
        $parsed = [];
        foreach ($lines ?: [] as $line) {
            if (! is_string($line) || ! str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $key = strtolower($name);
            $parsed[$key] = isset($parsed[$key]) ? $parsed[$key] . ', ' . $value : $value;
        }

        return $parsed;
    }
}
