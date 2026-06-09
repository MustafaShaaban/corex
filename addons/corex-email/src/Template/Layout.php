<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Template;

defined('ABSPATH') || exit;

/**
 * Wraps a rendered body in a shared, on-brand HTML email shell. The brand name,
 * accent color, logo, and text direction are runtime values (from `brand.json`),
 * so a rebrand is configuration (Principle V). Email clients do not support CSS
 * custom properties, so the shell uses inline styles; the only literals are
 * functional email-layout structure (the de-facto 600px body width and spacing),
 * never design tokens — the brand values are injected, not hardcoded.
 */
final class Layout
{
    /**
     * @param array{name?:string,color?:string,logo?:string,dir?:string} $brand
     */
    public function __construct(private readonly array $brand = [])
    {
    }

    public function wrap(string $subject, string $bodyHtml): string
    {
        $dir   = ($this->brand['dir'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr';
        $name  = $this->escape($this->brand['name'] ?? '');
        $color = $this->brand['color'] ?? '';
        $logo  = $this->brand['logo'] ?? '';

        $header = $logo !== ''
            ? sprintf('<img src="%s" alt="%s" style="max-width:180px;height:auto" />', $this->escape($logo), $name)
            : sprintf('<strong>%s</strong>', $name);

        $accent = $color !== '' ? sprintf('border-block-start:4px solid %s;', $this->escape($color)) : '';

        return sprintf(
            '<!DOCTYPE html><html dir="%1$s"><head><meta charset="utf-8" />'
            . '<meta name="viewport" content="width=device-width, initial-scale=1" />'
            . '<title>%2$s</title></head>'
            . '<body style="margin:0;padding:0">'
            . '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0"><tr>'
            . '<td align="center" style="padding:24px">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;%3$s">'
            . '<tr><td style="padding:24px;text-align:start">%4$s</td></tr>'
            . '<tr><td style="padding:0 24px 24px;text-align:start">%5$s</td></tr>'
            . '</table></td></tr></table></body></html>',
            $dir,
            $this->escape($subject),
            $accent,
            $header,
            $bodyHtml,
        );
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
