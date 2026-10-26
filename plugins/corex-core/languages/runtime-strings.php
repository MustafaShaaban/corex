<?php

/**
 * Strings CoreX translates at run time from a value it holds, written here so the translation
 * template lists them (spec 121, US-NFR-02, DECISIONS #467).
 *
 * A managed table names itself and its columns in English and translates the names when a screen
 * shows them (`Corex\Database\Schema\ManagedTable::displayLabel()`), so no `__()` call in its
 * source holds the words. This file is never loaded; `wp i18n make-pot` reads it.
 * `tests/Unit/I18n/RuntimeStringsTest.php` fails when a table adds a name this list lacks.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;

return;

// phpcs:disable Squiz.PHP.NonExecutableCode.Unreachable
__('Job applications', 'corex');
__('Applicant', 'corex');
__('Email', 'corex');
__('Job', 'corex');
__('CV', 'corex');
__('Status', 'corex');
__('Applied', 'corex');
__('Newsletter subscribers', 'corex');
__('Topics', 'corex');
__('Consent', 'corex');
__('Subscribed', 'corex');
__('Role ability grants', 'corex');
__('Access requests', 'corex');
__('Role', 'corex');
__('Ability', 'corex');
__('Effect', 'corex');
__('Source', 'corex');
__('Updated', 'corex');
__('Requester', 'corex');
__('Area', 'corex');
__('State', 'corex');
__('Created', 'corex');
__('Expires', 'corex');
__('Activity events', 'corex');
__('Occurred', 'corex');
__('Event', 'corex');
__('Actor', 'corex');
__('Target', 'corex');
__('Outcome', 'corex');
__('Blog reading events', 'corex');
__('Post', 'corex');
__('Reading seconds', 'corex');
__('Share target', 'corex');
__('Retention until', 'corex');
__('Bounded jobs', 'corex');
__('Kind', 'corex');
__('Processed', 'corex');
__('Total', 'corex');
__('Attempts', 'corex');
__('Notifications', 'corex');
__('Latest', 'corex');
__('Category', 'corex');
__('Severity', 'corex');
__('Type', 'corex');
__('Count', 'corex');
__('Resolved', 'corex');
__('Notification user state', 'corex');
__('Notification', 'corex');
__('User', 'corex');
__('Read', 'corex');
__('Dismissed', 'corex');
__('Login attempts', 'corex');
__('Reason', 'corex');
