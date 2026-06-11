<?php

/**
 * Feature flags (lowest-precedence defaults). Each flag is read via the layered
 * Config engine, so any flag can be flipped per-site by a WordPress option
 * (`corex_features_<flag>`) or the project `.env` (`FEATURES_<FLAG>`), without code.
 *
 * This file is also the **registry** of known flags — `FeatureFlags::all()` reports
 * each one's resolved state. Truthy env/option values (`1/true/on/yes`) enable a flag.
 *
 * @package Corex
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    // Edition gate — Pro features ride on this (Free build leaves it false).
    'pro' => false,

    // Deferred / opt-in capabilities (off until their module is built + enabled).
    'mail_queue'      => false, // Action Scheduler-backed bulk mail (newsletter sends)
    'dataviews_admin' => false, // React/DataViews admin UI (tables, setup wizard, health-check)
    'woocommerce_kit' => false, // WooCommerce site kit + woo-guarded features
];
