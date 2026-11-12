<?php

/**
 * Link and contact helpers for the theme's block patterns (spec 115, FR-015 and FR-016).
 *
 * A pattern file is evaluated when its content is needed, so it can ask the site where a link goes. Each helper
 * answers with a real address or with nothing: a pattern links to a published page of the site, the blog page, the
 * home page or a brand setting, and leaves a link, button or contact detail out when there is none. Presentational
 * only: these read settings and pages, they never write.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;

if (! function_exists('corex_pattern_page_url')) {
    /**
     * The permalink of the published page at a path, or '' when there is none.
     *
     * @param string $slug Page path, e.g. `about` or `company/careers`.
     */
    function corex_pattern_page_url(string $slug): string
    {
        $page = get_page_by_path($slug);

        if (! $page instanceof WP_Post || $page->post_status !== 'publish') {
            return '';
        }

        $url = get_permalink($page);

        return is_string($url) ? $url : '';
    }
}

if (! function_exists('corex_pattern_anchor')) {
    /**
     * A link to an address, or the label alone as text when the address is ''.
     *
     * @param string $url   Address to link to; '' renders the label without a link.
     * @param string $label Visible text.
     */
    function corex_pattern_anchor(string $url, string $label): string
    {
        if ($url === '') {
            return esc_html($label);
        }

        return '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }
}

if (! function_exists('corex_pattern_link')) {
    /**
     * A link to the published page at a path, or the label alone as text when the page does not exist.
     *
     * @param string $slug  Page path.
     * @param string $label Visible text.
     */
    function corex_pattern_link(string $slug, string $label): string
    {
        return corex_pattern_anchor(corex_pattern_page_url($slug), $label);
    }
}

if (! function_exists('corex_pattern_posts_url')) {
    /**
     * Where the site lists its posts, or '' when it has no such page.
     *
     * With a static front page this is the published posts page (`page_for_posts`); with the latest posts on the front
     * page it is the home page.
     */
    function corex_pattern_posts_url(): string
    {
        if (get_option('show_on_front') !== 'page') {
            return home_url('/');
        }

        $posts_page = (int) get_option('page_for_posts');

        if ($posts_page <= 0 || get_post_status($posts_page) !== 'publish') {
            return '';
        }

        $url = get_permalink($posts_page);

        return is_string($url) ? $url : '';
    }
}

if (! function_exists('corex_pattern_brand')) {
    /**
     * A brand setting (`brand.<key>`) as a trimmed string, or '' when it is not set or the framework is not active.
     *
     * @param string $key Setting key under `brand.`, e.g. `email`, `phone`, `address`, `social_links`.
     */
    function corex_pattern_brand(string $key): string
    {
        if (! class_exists('\Corex\Support\Facades\Config')) {
            return '';
        }

        try {
            $value = \Corex\Support\Facades\Config::get('brand.' . $key, '');
        } catch (\Throwable) {
            return '';
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }
}

if (! function_exists('corex_pattern_action_url')) {
    /**
     * Where a generic call-to-action goes: the brand's primary action link, else the published `contact` page, else ''.
     */
    function corex_pattern_action_url(): string
    {
        $link = corex_pattern_brand('primary_action_link');

        return $link !== '' ? $link : corex_pattern_page_url('contact');
    }
}

if (! function_exists('corex_pattern_button')) {
    /**
     * A serialized `core/button` block linking to an address, or '' when the address is '' (the button is left out).
     *
     * The wrapper classes are derived from the two attributes the theme's patterns use, `className` and `fontSize`, so
     * the markup matches what the editor saves for them.
     *
     * @param string                                    $url        Address the button links to.
     * @param string                                    $label      Visible text.
     * @param array{className?:string,fontSize?:string} $attributes Block attributes.
     */
    function corex_pattern_button(string $url, string $label, array $attributes = []): string
    {
        // An address esc_url() refuses is no address: the button is left out.
        $url = esc_url($url);
        if ($url === '') {
            return '';
        }

        $classes = ['wp-block-button'];

        if (isset($attributes['fontSize'])) {
            $classes[] = 'has-custom-font-size';
            $classes[] = 'has-' . $attributes['fontSize'] . '-font-size';
        }

        if (isset($attributes['className'])) {
            $classes[] = $attributes['className'];
        }

        $html = '<div class="' . esc_attr(implode(' ', $classes)) . '">'
            . '<a class="wp-block-button__link wp-element-button" href="' . $url . '">' . esc_html($label) . '</a>'
            . '</div>';

        return get_comment_delimited_block_content('core/button', $attributes, $html);
    }
}

if (! function_exists('corex_pattern_social_links')) {
    /**
     * Serialized `core/social-link` blocks for the brand's social profiles (`brand.social_links`, comma-separated), or
     * '' when none is set.
     *
     * The service (the icon) is read from the address's host; an unknown host gets the generic link icon.
     */
    function corex_pattern_social_links(): string
    {
        $services = [
            'github.com'    => 'github',
            'x.com'         => 'x',
            'twitter.com'   => 'x',
            'linkedin.com'  => 'linkedin',
            'facebook.com'  => 'facebook',
            'instagram.com' => 'instagram',
            'youtube.com'   => 'youtube',
            'tiktok.com'    => 'tiktok',
            'behance.net'   => 'behance',
            'dribbble.com'  => 'dribbble',
            'wordpress.org' => 'wordpress',
        ];

        $blocks = '';

        foreach (explode(',', corex_pattern_brand('social_links')) as $candidate) {
            $url = esc_url_raw(trim($candidate));

            if ($url === '') {
                continue;
            }

            $host    = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
            $host    = preg_replace('/^www\./', '', $host) ?? $host;
            $service = $services[$host] ?? 'chain';

            $blocks .= get_comment_delimited_block_content('core/social-link', ['url' => $url, 'service' => $service], '');
        }

        return $blocks;
    }
}

if (! function_exists('corex_pattern_tel_url')) {
    /**
     * A `tel:` address for a phone number as written in the brand settings, or '' when it holds no digit.
     *
     * @param string $phone Phone number, e.g. `+20 2 1234 5678`.
     */
    function corex_pattern_tel_url(string $phone): string
    {
        $number = preg_replace('/[^0-9+]/', '', $phone) ?? '';

        return preg_match('/[0-9]/', $number) === 1 ? 'tel:' . $number : '';
    }
}

if (! function_exists('corex_pattern_mailto_url')) {
    /**
     * A `mailto:` address for an email address, or '' when it is not a valid address.
     *
     * @param string $email Email address.
     */
    function corex_pattern_mailto_url(string $email): string
    {
        $email = sanitize_email($email);

        return $email !== '' && is_email($email) ? 'mailto:' . $email : '';
    }
}

if (! function_exists('corex_pattern_contact')) {
    /**
     * One contact detail from the brand settings as escaped HTML, or '' when it is not set: the email and the phone
     * as links (`mailto:`, `tel:`), the address as text.
     *
     * @param string $key `email`, `phone` or `address`.
     */
    function corex_pattern_contact(string $key): string
    {
        $value = corex_pattern_brand($key);

        if ($value === '') {
            return '';
        }

        return match ($key) {
            'email' => corex_pattern_anchor(corex_pattern_mailto_url($value), $value),
            'phone' => corex_pattern_anchor(corex_pattern_tel_url($value), $value),
            default => esc_html($value),
        };
    }
}
