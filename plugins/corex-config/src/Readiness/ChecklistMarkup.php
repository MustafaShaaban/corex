<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Readiness;

defined('ABSPATH') || exit;

/**
 * The launch checklist's rows as server-drawn markup, shared by the Overview and the Hardening tab
 * of Operations & Security (spec 118). Each row says its state in a word beside an icon, inside the
 * row's own text, so a screen reader reads "HTTPS, Blocks going live, …" and nothing depends on
 * colour (US-SEC-09). An open row's label is the link to its fix; a done row is not a link.
 */
final class ChecklistMarkup
{
    /**
     * @param list<array{key:string,label:string,chip:string,word:string,note:string,href:string}> $rows
     */
    public function rows(array $rows): string
    {
        $items = '';
        foreach ($rows as $row) {
            $label = $row['href'] !== ''
                ? '<a href="' . esc_url($row['href']) . '">' . esc_html($row['label']) . '</a>'
                : esc_html($row['label']);

            $items .= '<li class="corex-checklist__row is-' . esc_attr($row['chip']) . '" data-check="' . esc_attr($row['key']) . '">'
                . '<span class="corex-checklist__label">' . $label . '</span>'
                . self::chip($row['chip'], $row['word'])
                . '<span class="corex-checklist__note">' . esc_html($row['note']) . '</span></li>';
        }

        return '<ul class="corex-checklist">' . $items . '</ul>';
    }

    /**
     * A progress bar for "N of M", named by what it measures and its value announced with the same
     * words the header shows.
     */
    public function progress(string $name, int $done, int $total, string $words): string
    {
        $percent = $total > 0 ? (int) round($done / $total * 100) : 0;

        return '<div class="corex-checklist__progress" role="progressbar" aria-label="' . esc_attr($name)
            . '" aria-valuemin="0" aria-valuemax="'
            . esc_attr((string) $total) . '" aria-valuenow="' . esc_attr((string) $done) . '" aria-valuetext="'
            . esc_attr($words) . '"><span style="inline-size:' . esc_attr((string) $percent) . '%"></span></div>';
    }

    /** The shared state chip: an icon and a word, in the state's tone (US-INS-02). */
    public static function chip(string $chip, string $word): string
    {
        return '<span class="corex-state-chip is-' . esc_attr($chip) . '">'
            . '<span class="corex-state-chip__icon" aria-hidden="true"></span>' . esc_html($word) . '</span>';
    }
}
