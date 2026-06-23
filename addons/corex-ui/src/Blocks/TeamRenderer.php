<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders a team as a responsive grid of member figures — each with an optional photo from the
 * media library, a name, a role, and a short bio. Members are a repeatable array attribute
 * (spec 029); a member with no name is skipped, and an empty team renders nothing.
 */
final class TeamRenderer implements BlockRenderer
{
    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $members = is_array($attributes['members'] ?? null) ? $attributes['members'] : [];

        $cards = '';

        foreach ($members as $member) {
            $cards .= $this->member(is_array($member) ? $member : []);
        }

        if ($cards === '') {
            return '';
        }

        return '<div class="corex-team">' . $cards . '</div>';
    }

    /**
     * @param array<string,mixed> $member
     */
    private function member(array $member): string
    {
        $name = trim((string) ($member['name'] ?? ''));

        if ($name === '') {
            return '';
        }

        $role = trim((string) ($member['role'] ?? ''));
        $bio  = trim((string) ($member['bio'] ?? ''));

        $html = '<figure class="corex-team__member">';
        $html .= $this->photo($member['image'] ?? null);
        $html .= '<figcaption class="corex-team__caption">';
        $html .= sprintf('<span class="corex-team__name">%s</span>', wp_kses_post($name));

        if ($role !== '') {
            $html .= sprintf('<span class="corex-team__role">%s</span>', wp_kses_post($role));
        }

        if ($bio !== '') {
            $html .= sprintf('<p class="corex-team__bio">%s</p>', wp_kses_post($bio));
        }

        return $html . '</figcaption></figure>';
    }

    /**
     * @param mixed $image the media attribute ({url, alt})
     */
    private function photo(mixed $image): string
    {
        $url = is_array($image) ? trim((string) ($image['url'] ?? '')) : '';

        if ($url === '') {
            return '';
        }

        $alt = is_array($image) ? (string) ($image['alt'] ?? '') : '';

        $img = sprintf(
            '<img class="corex-team__photo" src="%s" alt="%s" loading="lazy" decoding="async" />',
            esc_url($url),
            esc_attr($alt)
        );

        // Optimized <picture> delivery when Corex Media is active (no hard dependency; class preserved).
        return (string) apply_filters('corex_media_optimize_image', $img, [
            'url'     => $url,
            'alt'     => $alt,
            'class'   => 'corex-team__photo',
            'loading' => 'lazy',
        ]);
    }
}
