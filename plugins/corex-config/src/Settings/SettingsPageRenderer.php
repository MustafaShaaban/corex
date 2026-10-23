<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

/**
 * Draws one Settings page (spec 119): a form of its own with its sections, what each provider
 * still needs and its latest test, the add-on's state, and a save bar fixed to the bottom.
 *
 * The bar's Discard is a reset button and its Save a submit button, so both work without scripts;
 * the page's script adds the state ("No changes", "Unsaved changes", "All changes saved") and the
 * question before leaving with unsaved changes.
 */
final class SettingsPageRenderer
{
    /** How much of what was typed a refusal quotes. */
    private const QUOTED_AT_MOST = 80;

    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly SettingsForm $form,
        private readonly SettingsFieldValues $values,
        private readonly SettingsSectionStates $states,
        private readonly ProviderSetup $providers,
        private readonly ProviderTestService $tests,
    ) {
    }

    public function render(SettingsPage $page, string $action, string $nonceField, ?SettingsSaveResult $result): string
    {
        $readOnly = $this->states->forPage($page) === SettingsSectionState::Disabled;
        $sections = array_intersect_key($this->registry->sections(), array_flip($page->sections));

        $html = sprintf(
            '<form method="post" action="%1$s" class="corex-settings-form corex-settings-page" novalidate'
            . ' data-corex-settings-page="%2$s">%3$s<input type="hidden" name="corex_settings_page" value="%2$s" />',
            esc_url($action),
            esc_attr($page->key),
            $nonceField,
        );
        $html .= $readOnly && $page->addon !== null ? $this->addonOff($page->addon) : '';

        $state = $this->fieldStates($result);
        foreach ($sections as $sectionKey => $section) {
            $html .= sprintf(
                '<section id="corex-settings-section-%1$s" class="corex-settings-section">%2$s%3$s</section>',
                esc_attr((string) $sectionKey),
                count($sections) > 1
                    ? sprintf('<h2 id="corex-settings-heading-%1$s">%2$s</h2>', esc_attr((string) $sectionKey), esc_html($section['title']))
                    : '',
                $this->sectionBody((string) $sectionKey, $section, $state, $readOnly),
            );
        }

        $html .= ! $readOnly && $this->hasEditableField($sections) ? $this->saveBar($result !== null) : '';
        $html .= '</form>';

        return $html . ($result === null ? '' : $this->toast($result));
    }

    /**
     * The state each field is drawn with: a refused value as it was typed, so it can be put right.
     *
     * @return callable(string, string=):FieldState
     */
    private function fieldStates(?SettingsSaveResult $result): callable
    {
        return function (string $key, string $type = 'text') use ($result): FieldState {
            $fieldState = $this->values->state($key, $type);
            $typed      = $result?->typedInto(SettingsControls::nameOf($key));

            return $typed === null ? $fieldState : $fieldState->asTyped($typed);
        };
    }

    /**
     * What a section holds: what its providers need, its fields, and any panel an add-on draws
     * under it. While its add-on is off, only its fields, read-only.
     *
     * @param array{title:string,fields:array<string,array<string,mixed>>} $section
     */
    private function sectionBody(string $sectionKey, array $section, callable $state, bool $readOnly): string
    {
        $fields = $this->form->rows($sectionKey, $section, $state, $readOnly);

        return $readOnly ? $fields : $this->providerNotices($sectionKey) . $fields . $this->extras($sectionKey);
    }

    /**
     * Whether the page has a field to save; Advanced is read-outs only, and has no save bar.
     *
     * @param array<string,array{fields:array<string,array<string,mixed>>}> $sections
     */
    private function hasEditableField(array $sections): bool
    {
        foreach ($sections as $section) {
            foreach ($section['fields'] as $field) {
                if (($field['type'] ?? '') !== 'info') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * What the add-on's page says while the add-on is off (US-SET-07): its name, that these
     * settings apply when it is on, and the way to its card.
     */
    private function addonOff(SettingsAddon $addon): string
    {
        return sprintf(
            '<div class="corex-section-notice corex-section-notice--disabled corex-settings-addon-off">'
            . '<p>%1$s</p><a class="button" href="%2$s">%3$s</a></div>',
            /* translators: %s: an add-on's name, such as Corex Media. */
            esc_html(sprintf(__('%s is off. These settings apply when it is on.', 'corex'), $addon->name)),
            esc_url(admin_url('admin.php?page=corex-addons') . '#' . $addon->cardAnchor()),
            esc_html__('Open Add-ons', 'corex'),
        );
    }

    /** Panels an add-on draws under its section (Media's library conversion), each escaped by its maker. */
    private function extras(string $sectionKey): string
    {
        return (string) apply_filters('corex_settings_section_extra', '', $sectionKey);
    }

    /** What each provider still needs, and its latest test (US-SET-03). */
    private function providerNotices(string $sectionKey): string
    {
        return match ($sectionKey) {
            'captcha'  => $this->notice($this->providers->captchaNeed())
                . ($this->providers->captchaTestable() ? $this->status(ProviderTestLog::CAPTCHA) : ''),
            'insights' => $this->notice($this->providers->pageSpeedNeed())
                . $this->notice($this->providers->cloudflareNeed())
                . $this->providerTest(
                    ProviderTestLog::PAGESPEED,
                    __('PageSpeed Insights key', 'corex'),
                    __('Asks Google whether it accepts the key, without running a measurement.', 'corex'),
                )
                . $this->providerTest(
                    ProviderTestLog::CLOUDFLARE,
                    __('Cloudflare API token', 'corex'),
                    __('Asks Cloudflare whether it accepts the token. The account ID is checked when a scan runs.', 'corex'),
                ),
            default => '',
        };
    }

    private function notice(string $message): string
    {
        return $message === ''
            ? ''
            : '<p class="corex-section-notice corex-section-notice--configuration-needed">' . esc_html($message) . '</p>';
    }

    private function status(string $provider): string
    {
        return sprintf(
            '<p class="corex-provider-status" data-corex-provider-status="%1$s">%2$s</p>',
            esc_attr($provider),
            esc_html($this->tests->statusLine($provider)),
        );
    }

    /** A provider's "Test connection", offered once it has a key. */
    private function providerTest(string $provider, string $name, string $what): string
    {
        if (! $this->tests->testable($provider)) {
            return '';
        }

        return sprintf(
            '<div class="corex-provider-test" data-corex-provider-test="%1$s">'
            . '<p class="corex-provider-test__name">%2$s</p>%3$s'
            . '<p class="corex-provider-test__actions"><button type="button" class="button" data-corex-provider-test-button>%4$s</button>'
            . ' <span class="corex-provider-test__result" role="status" aria-live="polite"></span></p>'
            . '<p class="corex-field-help">%5$s</p></div>',
            esc_attr($provider),
            esc_html($name),
            $this->status($provider),
            esc_html__('Test connection', 'corex'),
            esc_html($what),
        );
    }

    /**
     * The bar fixed to the bottom of the page. Its words for each state are written here, where
     * they are translated, and read by the page's script.
     */
    private function saveBar(bool $justSaved): string
    {
        return sprintf(
            '<div class="corex-savebar" data-corex-savebar data-state="%1$s" data-text-clean="%2$s"'
            . ' data-text-dirty="%3$s" data-text-saved="%4$s" data-text-leave="%5$s" data-text-failed="%9$s">'
            . '<p class="corex-savebar__status"><span class="corex-savebar__dot" aria-hidden="true"></span>'
            . '<span data-corex-savebar-text>%6$s</span></p>'
            . '<p class="corex-savebar__error" role="alert" data-corex-savebar-error hidden></p>'
            . '<div class="corex-savebar__actions">'
            . '<button type="reset" class="button" data-corex-discard>%7$s</button>'
            . '<button type="submit" class="button button-primary" data-corex-save>%8$s</button></div></div>',
            $justSaved ? 'saved' : 'clean',
            esc_attr__('No changes', 'corex'),
            esc_attr__('Unsaved changes', 'corex'),
            esc_attr__('All changes saved', 'corex'),
            esc_attr__('You have unsaved changes on this page. Leave without saving them?', 'corex'),
            $justSaved ? esc_html__('All changes saved', 'corex') : esc_html__('No changes', 'corex'),
            esc_html__('Discard', 'corex'),
            esc_html__('Save changes', 'corex'),
            esc_attr__('The save did not go through, so nothing was changed. Your edits are still here: try again.', 'corex'),
        );
    }

    /**
     * What a save says (spec 106 story 5, spec 119): "Settings saved." as a status, or, when a
     * field was refused, an alert naming each field, what was typed and why, which stays until it
     * is dismissed. A removed secret is named either way.
     */
    private function toast(SettingsSaveResult $result): string
    {
        $removed = '';
        foreach ($result->removed as $key) {
            $removed .= '<p class="corex-toast__message">' . esc_html(sprintf(
                /* translators: %s: a setting's label, such as Secret key. */
                __('%s was removed.', 'corex'),
                $this->labelOf($key),
            )) . '</p>';
        }

        if ($result->refused === []) {
            return sprintf(
                '<div class="corex-toast corex-toast--success" role="status" data-corex-toast>'
                . '<div class="corex-toast__messages"><p class="corex-toast__message">%1$s</p>%2$s</div>'
                . '<button type="button" class="corex-toast__dismiss" data-corex-toast-dismiss aria-label="%3$s">&times;</button>'
                . '</div>',
                esc_html__('Settings saved.', 'corex'),
                $removed,
                esc_attr__('Dismiss', 'corex'),
            );
        }

        $lines = '';
        foreach ($result->refused as $field) {
            // What was typed keeps its own direction: in a right-to-left sentence "answers@" would
            // otherwise be drawn as "@answers".
            $lines .= sprintf(
                '<p class="corex-toast__message" id="corex-refused-%1$s" data-corex-refused="%1$s">%2$s</p>',
                esc_attr($field['name']),
                sprintf(
                    esc_html($this->whyRefused($field['type'])),
                    esc_html($field['label']),
                    '<bdi>' . esc_html(self::asTyped($field['typed'])) . '</bdi>',
                ),
            );
        }

        // It can be given focus, which the page's script does when it loads: an alert already in
        // a page as it arrives is not reliably read out.
        return sprintf(
            '<div class="corex-toast corex-toast--warning" role="alert" tabindex="-1" data-corex-toast data-corex-toast-stays>'
            . '<div class="corex-toast__messages">%1$s%2$s<p class="corex-toast__message">%3$s</p></div>'
            . '<button type="button" class="corex-toast__dismiss" data-corex-toast-dismiss aria-label="%4$s">&times;</button>'
            . '</div>',
            $lines,
            $removed,
            esc_html__('Everything else was saved.', 'corex'),
            esc_attr__('Dismiss', 'corex'),
        );
    }

    private function labelOf(string $key): string
    {
        foreach ($this->registry->sections() as $section) {
            if (isset($section['fields'][$key]['label'])) {
                return (string) $section['fields'][$key]['label'];
            }
        }

        return $key;
    }

    /** What was typed, short enough to quote: a picture pasted as `data:` is thousands of characters. */
    private static function asTyped(string $typed): string
    {
        $text = trim(wp_check_invalid_utf8($typed, true));

        return mb_strlen($text) > self::QUOTED_AT_MOST ? mb_substr($text, 0, self::QUOTED_AT_MOST) . '…' : $text;
    }

    /** The sentence for a value a field of that type does not take. */
    private function whyRefused(string $type): string
    {
        return match ($type) {
            /* translators: 1: a setting's label. 2: what was typed into it. */
            'email' => __('%1$s was not changed: "%2$s" is not an email address.', 'corex'),
            /* translators: 1: a setting's label. 2: what was typed into it. */
            'url', 'media' => __('%1$s was not changed: "%2$s" is not a web address.', 'corex'),
            /* translators: 1: a setting's label. 2: what was typed into it. */
            'hex-colour' => __('%1$s was not changed: "%2$s" is not a six-digit colour such as #0B1F3B.', 'corex'),
            /* translators: 1: a setting's label. 2: what was chosen for it. */
            default => __('%1$s was not changed: "%2$s" is not one of its choices.', 'corex'),
        };
    }
}
