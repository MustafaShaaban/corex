<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media\Conversion;

defined('ABSPATH') || exit;

/**
 * "Convert existing library" under Settings > Media (spec 119, US-SET-06), handed to the Settings
 * page through `corex_settings_section_extra`, so corex-config never depends on this add-on.
 *
 * Drawn with the run as it stands; its script follows a running conversion, starts one and
 * cancels one. The buttons wait for the script: without it there is nothing to drive the run.
 */
final class MediaConversionPanel
{
    private const SCREEN_SUFFIX = '_page_corex-settings-config';

    public function __construct(private readonly MediaConversionService $conversion)
    {
    }

    public function register(): void
    {
        add_filter('corex_settings_section_extra', [$this, 'extra'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function extra(mixed $html, mixed $sectionKey): string
    {
        $html = (string) $html;

        return $sectionKey === 'media' ? $html . $this->render() : $html;
    }

    public function enqueue(string $hook): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which Settings page is open.
        $page = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : '';
        if (! str_ends_with($hook, self::SCREEN_SUFFIX) || $page !== 'media') {
            return;
        }

        $script = dirname(COREX_MEDIA_FILE) . '/assets/media-conversion.js';
        wp_enqueue_script(
            'corex-media-conversion',
            plugins_url('assets/media-conversion.js', COREX_MEDIA_FILE),
            ['corex-runtime'],
            (string) filemtime($script),
            true,
        );
        wp_localize_script('corex-media-conversion', 'corexMediaConversion', [
            'restUrl' => esc_url_raw(rest_url('corex/v1/media/conversion')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'i18n'    => [
                'failedRequest' => __('The request did not get an answer. Try again.', 'corex'),
                'failures'      => __('Images that could not be converted', 'corex'),
            ],
        ]);
    }

    public function render(): string
    {
        $reason = $this->conversion->unavailableReason();
        $run    = $this->conversion->status();

        return sprintf(
            '<div class="corex-media-conversion" data-corex-media-conversion data-available="%1$s">'
            . '<h3 class="corex-media-conversion__title">%2$s</h3>'
            . '<p class="corex-field-help">%3$s</p>%4$s'
            . '<p class="corex-media-conversion__actions">'
            . '<button type="button" class="button" data-corex-conversion-start hidden%5$s>%2$s</button>'
            . '<button type="button" class="button" data-corex-conversion-cancel hidden>%6$s</button></p>'
            . '<progress class="corex-media-conversion__progress" data-corex-conversion-progress max="%7$d" value="%8$d"%9$s></progress>'
            . '<p class="corex-media-conversion__summary" role="status" data-corex-conversion-summary>%10$s</p>'
            . '%11$s</div>',
            $reason === '' ? '1' : '0',
            esc_html__('Convert existing library', 'corex'),
            esc_html__('Writes a WebP copy of each JPEG and PNG already in the Media library. Originals are kept, and images that already have a WebP copy are skipped.', 'corex'),
            $reason === '' ? '' : '<p class="corex-section-notice corex-section-notice--configuration-needed">' . esc_html($reason) . '</p>',
            $reason === '' ? '' : ' disabled',
            esc_html__('Cancel', 'corex'),
            max(1, (int) ($run['total'] ?? 1)),
            (int) ($run['processed'] ?? 0),
            $run === null || ! $run['active'] ? ' hidden' : '',
            esc_html($run === null ? __('Not run yet.', 'corex') : (string) $run['summary']),
            $this->failures($run['failures'] ?? []),
        );
    }

    /** @param list<array{file:string,reason:string}> $failures */
    private function failures(array $failures): string
    {
        $items = '';
        foreach ($failures as $failure) {
            $items .= sprintf('<li><bdi>%1$s</bdi>: %2$s</li>', esc_html($failure['file']), esc_html($failure['reason']));
        }

        return sprintf(
            '<div class="corex-media-conversion__failures" data-corex-conversion-failures%1$s>'
            . '<p class="corex-media-conversion__failures-title">%2$s</p><ul>%3$s</ul></div>',
            $items === '' ? ' hidden' : '',
            esc_html__('Images that could not be converted', 'corex'),
            $items,
        );
    }
}
