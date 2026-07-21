<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

/**
 * Derives and normalises the reCAPTCHA action for a form.
 *
 * This is the single source of the action value. The block renderer calls it to stamp the
 * form, and the protection stage calls it to compute the expectation the token is judged
 * against. Because both sides call the *same* function with the same input, the browser cannot
 * use an action the server does not expect (FR-004) — the check is structural, not merely
 * intended.
 *
 * It lives in the Forms layer, not the captcha add-on: the action is a Forms concept (derived
 * from a flow slug), and the verifier only *compares* the action it is handed. Keeping it here
 * means a site with `corex-forms` but no captcha add-on has no missing dependency.
 */
final class CaptchaAction
{
    /** Google actions permit A–Z a–z 0–9 / _ - and are bounded in length. */
    private const MAX_LENGTH = 100;
    private const DERIVED_PREFIX = 'corex_form_';

    /**
     * The action for a form: the explicit override when it normalises to something usable,
     * otherwise a deterministic value derived from the flow slug. Never returns an empty
     * string — an empty action is one the provider rejects.
     */
    public static function forFlow(string $slug, ?string $override): string
    {
        $normalisedOverride = self::normalise($override);
        if ($normalisedOverride !== null) {
            return $normalisedOverride;
        }

        // sanitize_key gives lowercase [a-z0-9_-]; hyphens read better as underscores in an
        // action label, and the prefix keeps two unrelated forms from colliding on a bare
        // generic action.
        $base = str_replace('-', '_', sanitize_key($slug));

        return substr(self::DERIVED_PREFIX . $base, 0, self::MAX_LENGTH);
    }

    /**
     * Reduce a raw string to the provider-safe action charset within the length bound, or
     * null when nothing usable remains.
     */
    public static function normalise(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $safe = (string) preg_replace('/[^A-Za-z0-9\/_-]/', '', $raw);
        if ($safe === '') {
            return null;
        }

        return substr($safe, 0, self::MAX_LENGTH);
    }
}
