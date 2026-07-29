<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides\Support;

defined('ABSPATH') || exit;

/**
 * The CoreX admin palette, in the only form an email can use (spec 093).
 *
 * **This file deliberately holds literals, and that needs explaining**, because
 * {@see \Corex\Email\Template\Layout} states the opposite rule for itself: *"the only literals are
 * functional email-layout structure … never design tokens — the brand values are injected, not
 * hardcoded."* That rule is right for `Layout`, which wraps every site's mail and must therefore
 * carry no opinion. Two things make it inapplicable here:
 *
 * 1. **An email cannot read `--corex-admin-*`.** Custom properties are not supported across mail
 *    clients, so a token has to arrive as a literal somewhere. The question is only whether it
 *    arrives in one named place or scattered through markup.
 * 2. **The brass identity is not in the brand pipeline.** `Layout`'s injected accent comes from
 *    `theme.json`'s `primary` — navy `#0B1F3B`. The admin's brass lives in
 *    `plugins/corex-core/assets/css/corex-admin-tokens.css` and nowhere else, so "use the CoreX
 *    design" and "inject the brand colour" name different colours. The owner asked for the former.
 *
 * The **light** values are used, not the dark defaults: dark-mode email support is unreliable, and a
 * message that renders as dark-on-dark in the client that does not honour it is worse than one that
 * is simply light everywhere.
 *
 * If the brass ever reaches `theme.json`, this class should be deleted and the values injected.
 */
final class SupportEmailPalette
{
    /** `--corex-admin-action` (light). The brass the admin is recognised by. */
    public const ACTION = '#ad8643';

    /** `--corex-admin-action-ink` (light) — what sits on top of the action colour. */
    public const ACTION_INK = '#ffffff';

    /** `--corex-admin-text` (light). */
    public const TEXT = '#14151a';

    /** `--corex-admin-text-muted` (light). */
    public const TEXT_MUTED = '#5b616d';

    /** `--corex-admin-canvas` (light) — the page behind the card. */
    public const CANVAS = '#f6f7f9';

    /** `--corex-admin-surface-raised` (light) — the card itself. */
    public const SURFACE = '#ffffff';

    /** `--corex-admin-border` (light). */
    public const BORDER = '#e2e5ea';

    /** `--corex-admin-radius-md`, in the pixels a mail client will honour. */
    public const RADIUS = '10px';

    /** `--corex-admin-space-lg`. */
    public const SPACE = '24px';

    /**
     * `--corex-admin-font-body`.
     *
     * Not the display face: `"CoreX Space Grotesk"` is a self-hosted WOFF2, and a mail client will
     * not load it. Naming it here would produce a silent fallback that differs per client rather
     * than a chosen one.
     *
     * **Single quotes are required.** This value goes inside `style="…"`, and a double-quoted family
     * name closes the attribute — which is not a rendering nuance but a broken tag, and it is what
     * the first draft of this file shipped.
     */
    public const FONT = "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";

    /** `--corex-admin-line-normal`. */
    public const LINE_HEIGHT = '1.6';
}
