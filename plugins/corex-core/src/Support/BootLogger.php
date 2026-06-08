<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support;

defined('ABSPATH') || exit;

/**
 * Records boot-time problems without ever aborting boot (constitution Principle II).
 *
 * Every message is written to the WordPress debug log. When debug mode is on, the
 * messages are additionally surfaced as a single dismissible admin notice, gated to
 * users who can manage options and escaped on output (spec FR-023, SC-008).
 *
 * PSR-3-shaped (warning/error) so it can later delegate to a full PSR-3 logger.
 */
final class BootLogger
{
    /**
     * @var list<array{level: string, message: string}>
     */
    private array $messages = [];

    private bool $noticeHooked = false;

    /**
     * @param bool $debug Whether to surface notices in the admin (typically WP_DEBUG).
     */
    public function __construct(private readonly bool $debug)
    {
    }

    public function warning(string $message): void
    {
        $this->record('warning', $message);
    }

    public function error(string $message): void
    {
        $this->record('error', $message);
    }

    /**
     * @return list<array{level: string, message: string}>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * Render queued notices. Hooked to `admin_notices` only in debug mode.
     */
    public function renderNotices(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        foreach ($this->messages as $entry) {
            printf(
                '<div class="notice notice-warning is-dismissible"><p><strong>%s</strong> %s</p></div>',
                esc_html__('Corex:', 'corex'),
                esc_html($entry['message'])
            );
        }
    }

    private function record(string $level, string $message): void
    {
        // Always write to the WordPress debug log (honors WP_DEBUG_LOG via error_log).
        error_log(sprintf('[Corex] %s: %s', strtoupper($level), $message));

        $this->messages[] = ['level' => $level, 'message' => $message];

        // Surface in the admin only in debug mode, and register the renderer once.
        if ($this->debug && ! $this->noticeHooked) {
            add_action('admin_notices', [$this, 'renderNotices']);
            $this->noticeHooked = true;
        }
    }
}
