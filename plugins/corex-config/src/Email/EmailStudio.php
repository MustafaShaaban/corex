<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Email;

defined('ABSPATH') || exit;

/**
 * Pure view model for the Email Studio admin screen (spec 063, Phase 2). It reports the REAL state of
 * the transactional-email engine: whether the optional corex-email add-on is active, which templates it
 * has actually registered, and an honest delivery-mode advisory derived from the site environment. It
 * never fabricates a template or claims that outbound email is suppressed when that is not wired — the
 * environment note advises, it does not assert behaviour the engine does not guarantee. WordPress-free.
 */
final class EmailStudio
{
    public const TONE_WARNING = 'warning';
    public const TONE_INFO    = 'info';

    /**
     * @param array{mode:string,label:string} $environment
     * @param list<string>                     $templates
     *
     * @return array{
     *   active:bool,
     *   templates:list<string>,
     *   templateCount:int,
     *   delivery:array{label:string,tone:string,detail:string},
     *   hasTemplates:bool
     * }
     */
    public function overview(bool $active, array $templates, array $environment): array
    {
        return [
            'active'        => $active,
            'templates'     => $templates,
            'templateCount' => count($templates),
            'delivery'      => $this->delivery($environment),
            'hasTemplates'  => $templates !== [],
        ];
    }

    /**
     * @param array{mode:string,label:string} $environment
     *
     * @return array{label:string,tone:string,detail:string}
     */
    private function delivery(array $environment): array
    {
        return match ($environment['mode']) {
            'production' => [
                'label'  => __('Live sending', 'corex'),
                'tone'   => self::TONE_WARNING,
                'detail' => __('This is a production site — transactional emails are delivered to real recipients.', 'corex'),
            ],
            'staging' => [
                'label'  => __('Staging', 'corex'),
                'tone'   => self::TONE_WARNING,
                'detail' => __('Staging environment — verify recipients before sending to avoid mailing real contacts.', 'corex'),
            ],
            default => [
                'label'  => __('Development', 'corex'),
                'tone'   => self::TONE_INFO,
                'detail' => __('Non-production environment — review outbound email and confirm your mail setup before going live.', 'corex'),
            ],
        };
    }
}
