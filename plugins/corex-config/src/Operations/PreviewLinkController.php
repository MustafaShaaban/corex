<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use Corex\Admin\StandalonePage;
use Corex\Security\Admin\AdminGuard;
use DateTimeImmutable;

/**
 * Receives an operator's action on the preview link (spec 101, FR-011 and FR-012): one
 * `admin_post` handler behind the shared {@see AdminGuard} (capability + nonce), which hands the
 * request to {@see PreviewLinkService} and reports what it answered.
 *
 * It reports in one of two ways, and the difference is the point of this class.
 *
 * An action that made no link — a revocation, or one with nothing to do — redirects back to
 * Operations & Security with a status, like every other form on that screen.
 *
 * An action that made a link answers the POST itself, with a page showing the link. It cannot
 * redirect: the link is not stored anywhere it could be read back from (FR-013), so the one moment
 * it exists is this response. Carrying it through a redirect would mean putting the secret in an
 * address — and so in the browser's history and the server's log — or parking it in the database
 * for the next request to collect, which is the thing the spec forbids.
 */
final class PreviewLinkController
{
    public const ACTION    = 'corex_preview_link';
    public const NONCE     = 'corex_preview_link_nonce';
    public const OPERATION = 'corex_preview_operation';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly PreviewLinkService $links,
        private readonly StandalonePage $page,
    ) {
    }

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    public function handle(): void
    {
        nocache_headers();

        if (! $this->guard->verifiedPost(self::NONCE, self::ACTION)) {
            status_header(403);
            $this->send($this->page->notice(
                __('Access denied', 'corex'),
                __('You are not allowed to change the preview link, or your link expired.', 'corex'),
                $this->screenUrl(),
                __('Back to Operations & Security', 'corex'),
            ));
        }

        $result = $this->links->perform(
            isset($_POST[self::OPERATION]) ? sanitize_key(wp_unslash($_POST[self::OPERATION])) : '',
            get_current_user_id(),
            new DateTimeImmutable('now'),
        );

        if ($result->token !== null) {
            $this->send($this->linkPage($result));
        }

        wp_safe_redirect(add_query_arg(['corex_status' => 'preview_' . $result->status], $this->screenUrl()));
        exit;
    }

    /**
     * The page that shows a link that was just made: the whole address, what it does, and that
     * this is the only time it will be shown.
     */
    public function linkPage(PreviewLinkResult $result): string
    {
        $regenerated = $result->status === PreviewLinkResult::REGENERATED;
        $title       = $regenerated
            ? __('Preview link regenerated', 'corex')
            : __('Preview link created', 'corex');
        $link        = add_query_arg([PreviewAccess::PARAMETER => (string) $result->token], home_url('/'));

        $body = '<main class="corex-standalone__card corex-standalone__card--wide" role="main">'
            . '<span class="corex-standalone__mark" aria-hidden="true">' . StandalonePage::brandMark() . '</span>'
            . '<p class="corex-standalone__eyebrow">Corex</p>'
            . '<h1 class="corex-standalone__title">' . esc_html($title) . '</h1>'
            . '<p class="corex-standalone__text">'
            . ($regenerated
                ? esc_html__('The previous link no longer works, and anybody who was using it now sees the coming-soon page.', 'corex') . ' '
                : '')
            . esc_html__('Copy this link now. CoreX does not keep it, so it cannot be shown again; if it is lost, regenerate it.', 'corex')
            . '</p>'
            . '<div class="corex-standalone__detail"><p>' . esc_html($link) . '</p></div>'
            . '<p class="corex-standalone__text">'
            . esc_html__('Whoever opens it sees the real site in that browser for 14 days, with a banner saying it is a private preview. It gives no access to the admin.', 'corex')
            . '</p>'
            . '<div class="corex-standalone__actions">'
            . '<a class="button button-primary" href="' . esc_url($this->screenUrl()) . '">'
            . esc_html__('Back to Operations & Security', 'corex') . '</a></div>'
            . '</main>';

        return $this->page->document($title, $body, 'notice');
    }

    private function send(string $document): never
    {
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        echo $document; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- StandalonePage returns a fully-escaped self-contained document.
        exit;
    }

    private function screenUrl(): string
    {
        return add_query_arg(
            ['page' => 'corex-operations-security', 'tab' => 'environment'],
            admin_url('admin.php'),
        );
    }
}
