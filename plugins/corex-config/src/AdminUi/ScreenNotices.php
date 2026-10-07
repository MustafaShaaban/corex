<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\AdminUi;

defined('ABSPATH') || exit;

/**
 * Moves what WordPress prints above the page into the CoreX shell, under the page header.
 *
 * ## What was wrong
 *
 * `wp-admin/admin-header.php` fires `admin_notices` and `all_admin_notices` as the first thing
 * inside `#wpbody-content`, before the page callback runs. On a CoreX screen that is above the
 * shell: core's update nag sat in a band of its own and pushed the whole product 54px down the
 * page, on every screen, on any site with a core update pending.
 *
 * WordPress's own script moves a notice under the page heading, and skips one marked `inline`.
 * The update nag is marked `inline`, so it stayed where it was printed.
 *
 * ## Why the output is captured, and not moved by a script or hidden
 *
 * A script would move the nag after the page had been drawn with it above the shell, so every
 * load would jump by its height. Hiding it until a script had moved it would hide a security
 * update from an administrator whenever that script did not run. Capturing the output on the
 * server puts it in the right place in the first response.
 *
 * Everything the two hooks print is taken, not only the nag: a plugin's notice marked `inline`
 * has the same defect, and one that is not is moved by core's script anyway.
 *
 * ## Why nothing can be lost
 *
 * The shell asks for the captured markup through the `corex_admin_notices` filter
 * ({@see \Corex\Admin\AdminPage::open()}). A page under the CoreX menu that draws no shell never
 * asks, so whatever is still held when the page callback has finished is printed there, as
 * WordPress printed it.
 *
 * The markup is printed as it was captured. It is output WordPress and other plugins had already
 * sent in this request, each having escaped its own; escaping it again here would remove the
 * links, forms and scripts a notice is made of.
 */
final class ScreenNotices
{
    /** The output-buffer depth of the capture while it is open. */
    private ?int $bufferLevel = null;

    private string $captured = '';

    public function register(): void
    {
        add_action('admin_notices', [$this, 'begin'], PHP_INT_MIN);
        add_action('all_admin_notices', [$this, 'end'], PHP_INT_MAX);
        add_filter('corex_admin_notices', [$this, 'place']);
    }

    public function begin(): void
    {
        if (! $this->onCorexScreen()) {
            return;
        }

        ob_start();
        $this->bufferLevel = ob_get_level();
    }

    public function end(): void
    {
        if ($this->bufferLevel === null) {
            return;
        }

        $level = $this->bufferLevel;
        $this->bufferLevel = null;

        // A callback that opened a buffer and left it open, or closed one it did not open, has
        // changed which buffer is on top. Closing it from here would take somebody else's output,
        // so the capture is abandoned: PHP sends every open buffer at the end of the request and
        // the notices appear where WordPress printed them.
        if (ob_get_level() !== $level) {
            return;
        }

        $this->captured = (string) ob_get_clean();

        // The hook `wp-admin/admin.php` calls the page through. WordPress keeps it in this global
        // and has no function that answers with it.
        $pageHook = (string) ($GLOBALS['page_hook'] ?? '');

        if ($this->captured !== '' && $pageHook !== '') {
            add_action($pageHook, [$this, 'printUnplaced'], PHP_INT_MAX);
        }
    }

    /**
     * Hands the captured notices to the shell, once: a second shell in the same request gets
     * nothing, so no notice is printed twice.
     *
     * A filter callback that also empties what it answers with. Answering without emptying would
     * need a second call to say "printed", and a filter has nowhere to make one.
     */
    public function place(string $markup): string
    {
        $captured = $this->captured;
        $this->captured = '';

        return $markup . $captured;
    }

    /**
     * Prints what no shell asked for, after the page callback and still inside the page body.
     */
    public function printUnplaced(): void
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output WordPress and other plugins printed in this request, held back and printed unchanged.
        echo $this->place('');
    }

    private function onCorexScreen(): bool
    {
        $screen = get_current_screen();

        return $screen !== null && CorexScreens::supports((string) $screen->id);
    }
}
