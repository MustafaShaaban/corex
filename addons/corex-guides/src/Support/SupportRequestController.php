<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides\Support;

defined('ABSPATH') || exit;

use Corex\Admin\StandalonePage;
use Corex\Security\Admin\AdminGuard;

/**
 * Where the Guides support form posts (spec 087, FR-005 / FR-007 / FR-008).
 *
 * POST → redirect → GET, so a refresh does not resend and the browser's back button lands on a
 * plain form rather than a resubmission prompt. The outcome travels in a per-user transient rather
 * than in the query string: the message is the person's own words, and a query string is written to
 * the web server log, kept in browser history, and forwarded in `Referer`.
 *
 * The `decide()` / `handle()` split is the one {@see \Corex\Config\Access\AccessRequestFormController}
 * uses, for the same reason: `exit` cannot be caught, so a test that drove the whole handler would
 * take the runner down with it. Every decision here is an ordinary return value; `handle()` is two
 * WordPress calls that a browser test exercises for real.
 */
final class SupportRequestController
{
    public const ACTION = 'corex_guides_support';
    public const NONCE  = 'corex_guides_support_nonce';

    /**
     * The form is offered to anybody who can open the Guides screen, and that screen asks only for
     * `read` — a guide is gated per guide, not per reader. Somebody who cannot find the answer is
     * exactly the person least likely to hold an admin ability, so requiring one would refuse help
     * to the people who need it.
     */
    private const CAPABILITY = 'read';

    /** One message per user per minute. Long enough to stop a double-click and a stuck key. */
    private const THROTTLE_SECONDS = 60;

    private const THROTTLE_PREFIX = 'corex_guides_support_sent_';

    /** How much of the message is carried, in characters. Beyond this it is an attachment, not a note. */
    private const MAX_MESSAGE = 4000;

    /**
     * What somebody can be asking about. Keys, not free text, so the subject line is predictable
     * and nothing the sender types can shape the email's own structure.
     *
     * @var list<string>
     */
    private const CATEGORIES = ['question', 'suggestion', 'missing', 'broken'];

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly SupportSettings $settings,
        private readonly SupportMailer $mailer,
        private readonly SupportFlash $flash,
    ) {
    }

    /** @return array<string,string> Category key => the label a reader chooses by. */
    public static function categories(): array
    {
        $labels = [];

        foreach (self::CATEGORIES as $key) {
            $labels[$key] = self::categoryLabel($key);
        }

        return $labels;
    }

    /**
     * Written as a `match` over literals rather than a lookup in a translated array, because the
     * string extractor reads `__()` calls in the source and cannot follow `__($array[$key])` — that
     * form compiles, ships, and is simply never translated.
     */
    private static function categoryLabel(string $key): string
    {
        return match ($key) {
            'suggestion' => __('A suggestion', 'corex'),
            'missing'    => __('Something is missing from the guides', 'corex'),
            'broken'     => __('Something is not working', 'corex'),
            default      => __('A question', 'corex'),
        };
    }

    /**
     * No `register()`: the provider hooks `admin_post_{ACTION}` to a closure that resolves this
     * class only when the action fires, so nothing here is built on a page that never posts.
     */
    public function handle(): void
    {
        $destination = $this->decide();

        if ($destination === null) {
            $this->refuse();
        }

        wp_safe_redirect($destination);
        exit;
    }

    /**
     * @return string|null Where to send the browser, or null when the submission is refused outright
     *                     — no session, or no valid nonce.
     */
    public function decide(): ?string
    {
        if (! $this->guard->verifiedPost(self::NONCE, self::ACTION, self::CAPABILITY)) {
            return null;
        }

        $screen = admin_url('admin.php?page=corex-guides');
        $userId = get_current_user_id();

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
        $trap = isset($_POST['corex_guides_website']) ? sanitize_text_field(wp_unslash($_POST['corex_guides_website'])) : '';
        // A filled honeypot is a bot. It gets the same answer a person gets, because telling it
        // apart from a real submission is the one thing that would make the trap worth defeating.
        if ($trap !== '') {
            $this->flash->store($userId, SupportFlash::SENT);

            return $screen;
        }

        if (! $this->settings->configured()) {
            $this->flash->store($userId, SupportFlash::UNAVAILABLE);

            return $screen;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
        $category = isset($_POST['corex_guides_category']) ? sanitize_key(wp_unslash($_POST['corex_guides_category'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
        $message = isset($_POST['corex_guides_message']) ? sanitize_textarea_field(wp_unslash($_POST['corex_guides_message'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
        $from = isset($_POST['corex_guides_email']) ? sanitize_email(wp_unslash($_POST['corex_guides_email'])) : '';

        if (! in_array($category, self::CATEGORIES, true)) {
            $category = self::CATEGORIES[0];
        }

        if (trim($message) === '') {
            $this->flash->store($userId, SupportFlash::EMPTY_MESSAGE, $message);

            return $screen;
        }

        // Server-side, so a second tab, a double-click and a replayed POST are all covered. A
        // disabled button covers none of them.
        if ($this->throttled($userId)) {
            $this->flash->store($userId, SupportFlash::THROTTLED, $message);

            return $screen;
        }

        $delivery = $this->mailer->send(
            $this->settings->recipient(),
            $this->subject($category),
            $this->body($category, $message, $from),
            $from,
        );

        if (! $delivery->sent) {
            // The typed text comes back with the form. Losing somebody's words because a mail
            // server was down would be the second failure in a row.
            $this->flash->store($userId, SupportFlash::FAILED, $message);

            return $screen;
        }

        $this->markSent($userId);
        $this->flash->store($userId, SupportFlash::SENT);

        return $screen;
    }

    private function subject(string $category): string
    {
        return sprintf(
            /* translators: 1: the site name, 2: what the message is about. */
            __('[%1$s] Guides support: %2$s', 'corex'),
            wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES),
            self::categoryLabel($category),
        );
    }

    /**
     * The message, plus the context that makes it answerable: who sent it and from which site.
     * Deliberately plain text — this goes to a person's inbox, not to a rendering surface.
     */
    private function body(string $category, string $message, string $from): string
    {
        $user = wp_get_current_user();

        $lines = [
            __('A CoreX Guides reader sent this from the admin.', 'corex'),
            '',
            /* translators: %s: what the message is about, e.g. "A suggestion". */
            sprintf(__('About: %s', 'corex'), self::categoryLabel($category)),
            /* translators: %s: the sender's display name. */
            sprintf(__('From: %s', 'corex'), $user->display_name !== '' ? $user->display_name : __('a signed-in user', 'corex')),
            /* translators: %s: the sender's email address. */
            sprintf(__('Reply to: %s', 'corex'), $from !== '' ? $from : __('no address given', 'corex')),
            /* translators: %s: the site's home URL. */
            sprintf(__('Site: %s', 'corex'), home_url()),
            '',
            __('Message:', 'corex'),
            mb_substr($message, 0, self::MAX_MESSAGE),
        ];

        return implode("\n", $lines);
    }

    private function throttled(int $userId): bool
    {
        return $userId > 0 && get_transient(self::THROTTLE_PREFIX . $userId) !== false;
    }

    private function markSent(int $userId): void
    {
        if ($userId > 0) {
            set_transient(self::THROTTLE_PREFIX . $userId, 1, self::THROTTLE_SECONDS);
        }
    }

    private function refuse(): never
    {
        status_header(403);
        nocache_headers();
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        echo StandalonePage::fromCore()->notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- StandalonePage returns a fully-escaped self-contained document.
            __('That message could not be sent', 'corex'),
            __('Your session or the link you used expired before the message was sent. Open the Guides screen again and resend it — nothing was recorded.', 'corex'),
            admin_url('admin.php?page=corex-guides'),
            __('Back to Guides', 'corex'),
        );
        exit;
    }
}
