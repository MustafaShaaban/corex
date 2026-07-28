<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Admin\Errors;

defined('ABSPATH') || exit;

use Corex\Admin\StandalonePage;

/**
 * Turns an {@see AdminError} into the branded document a refused person is shown (spec 083).
 *
 * These refusals happen before the admin shell exists — WordPress ends the request at the menu
 * gate, long before a screen's callback or its stylesheet — so the response has to be entirely
 * self-contained. Spec 079 wrote that document by hand in six places across corex-config, and they
 * drifted; the wording, the status and the way back all come from here now.
 *
 * There is no in-shell renderer here on purpose. A screen that is already rendering has
 * `AdminPage::state('error', …)` and, in React, `CorexErrorState` — adding a third with no caller
 * would be an abstraction kept alive by its own existence.
 */
final class AdminErrorPresenter
{
    public function __construct(private readonly StandalonePage $page)
    {
    }

    /**
     * Build the standard error for a kind, with CoreX's copy and a way forward.
     *
     * @param int|null $status the caller's own status, which always wins over the kind's default
     */
    public function make(AdminErrorKind $kind, ?int $status = null): AdminError
    {
        [$title, $message] = $this->copy($kind);

        return new AdminError(
            $kind,
            $title,
            $message,
            $status ?? $kind->status(),
            $this->defaultActions($kind),
        );
    }

    /**
     * A complete `<!DOCTYPE html>` response, for refusals that happen before the admin shell loads.
     */
    public function document(AdminError $error): string
    {
        return $this->page->document($error->title, $this->body($error), $error->kind->variant());
    }

    /**
     * The document body: either the caller's purpose-built surface, or the standard anatomy.
     */
    private function body(AdminError $error): string
    {
        $wide = $error->hasBody() ? ' corex-standalone__card--wide' : '';

        return '<main class="corex-standalone__card' . $wide . '" role="main">'
            . ($error->hasBody() ? $error->bodyHtml : $this->cardContents($error))
            . '</main>';
    }

    /**
     * Mark, eyebrow, title, explanation, whatever the original caller said, and the ways forward.
     */
    private function cardContents(AdminError $error): string
    {
        return '<span class="corex-standalone__mark" aria-hidden="true">'
            . StandalonePage::brandMark() . '</span>'
            . '<p class="corex-standalone__eyebrow">Corex</p>'
            . '<h1 class="corex-standalone__title">' . esc_html($error->title) . '</h1>'
            . '<p class="corex-standalone__text">' . esc_html($error->message) . '</p>'
            . $this->detailHtml($error)
            . $this->actionsHtml($error);
    }

    /**
     * What the original caller said, kept.
     *
     * Markup survives rather than being escaped, because WordPress builds real structure into some
     * of these messages — an expired-link notice carries the "go back" anchor that is the whole
     * point of the page, and escaping it would leave a person reading HTML for their way out
     * (FR-008).
     *
     * But not `wp_kses_post()`, which was the first attempt and looked wrong the moment it was
     * rendered: `wp-admin/users.php` passes `'<h1>You need a higher level of permission.</h1><p>…'`
     * as its message, so the quoted block arrived carrying a heading that outranked CoreX's own
     * title — a page with two competing headings, the louder one belonging to somebody else.
     * The allow-list keeps what makes a quotation readable and navigable, and drops the structure
     * that only the surrounding page is entitled to.
     */
    private function detailHtml(AdminError $error): string
    {
        if (trim($error->detail) === '') {
            return '';
        }

        $quoted = wp_kses($error->detail, [
            'a' => ['href' => [], 'rel' => [], 'target' => []],
            'p' => [],
            'br' => [],
            'em' => [],
            'strong' => [],
            'code' => [],
        ]);

        if (trim(wp_strip_all_tags($quoted)) === '') {
            return '';
        }

        return '<div class="corex-standalone__detail">' . $quoted . '</div>';
    }

    /**
     * @return string the actions block, or nothing when there is no honest way forward to offer
     */
    private function actionsHtml(AdminError $error): string
    {
        if ($error->actions === []) {
            return '';
        }

        $links = '';
        foreach ($error->actions as $action) {
            $links .= '<a class="button' . ($action['primary'] ? ' button-primary' : '') . '" href="'
                . esc_url($action['url']) . '">' . esc_html($action['label']) . '</a>';
        }

        return '<div class="corex-standalone__actions">' . $links . '</div>';
    }

    /**
     * @return array{0:string,1:string} title and explanation
     */
    private function copy(AdminErrorKind $kind): array
    {
        return match ($kind) {
            AdminErrorKind::Denied => [
                __('You don’t have access to this area', 'corex'),
                __('Your account doesn’t have permission to open this screen. A site administrator can grant it.', 'corex'),
            ],
            AdminErrorKind::NotFound => [
                __('That screen doesn’t exist', 'corex'),
                __('The address you opened doesn’t match any screen here. It may have been renamed, or the add-on that provided it may be inactive.', 'corex'),
            ],
            AdminErrorKind::Expired => [
                __('That link has expired', 'corex'),
                __('Admin links and forms stop working after a while, so that an old one can’t be used against you. Go back and try again.', 'corex'),
            ],
            AdminErrorKind::Session => [
                __('You’ve been signed out', 'corex'),
                __('Sign in again to carry on from where you were.', 'corex'),
            ],
            AdminErrorKind::RateLimited => [
                __('Too many attempts', 'corex'),
                __('That has been tried several times in quick succession. Wait a moment, then try once more.', 'corex'),
            ],
            AdminErrorKind::Unavailable => [
                __('Temporarily unavailable', 'corex'),
                __('Something this screen depends on isn’t responding right now. It should come back on its own.', 'corex'),
            ],
            AdminErrorKind::Failed => [
                __('Something went wrong', 'corex'),
                __('That action couldn’t be completed. If it keeps happening, tell a site administrator what you were doing.', 'corex'),
            ],
        };
    }

    /**
     * The ways forward for a kind.
     *
     * A rate limit and an outage get none: both are asking somebody to wait, and a button is an
     * invitation to do the thing that just failed.
     *
     * @return list<array{label:string,url:string,primary:bool}>
     */
    private function defaultActions(AdminErrorKind $kind): array
    {
        $dashboard = [
            'label' => __('Back to Dashboard', 'corex'),
            'url' => admin_url(),
            'primary' => true,
        ];

        return match ($kind) {
            AdminErrorKind::Session => [[
                'label' => __('Sign in', 'corex'),
                'url' => wp_login_url(),
                'primary' => true,
            ]],
            AdminErrorKind::RateLimited, AdminErrorKind::Unavailable => [],
            default => [$dashboard],
        };
    }
}
