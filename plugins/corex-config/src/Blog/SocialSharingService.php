<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Builds Blog share controls and records first-party share clicks.
 */
final class SocialSharingService
{
    /**
     * @var array<string,string|null>
     */
    private const PLATFORMS = [
        'x' => 'https://twitter.com/intent/tweet?url={url}&text={title}',
        'facebook' => 'https://www.facebook.com/sharer/sharer.php?u={url}',
        'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url={url}',
        'copy_link' => null,
    ];

    public function __construct(
        private readonly BlogAnalyticsService $analytics,
        private readonly SocialSharingSettingsStore $settings,
    ) {
    }

    /**
     * @return list<array{target:string,label:string,url:string}>
     */
    public function controls(int $postId, string $permalink, string $title): array
    {
        if ($postId < 1) {
            return [];
        }

        $controls = [];
        foreach ($this->settings->current()->enabledPlatforms as $target) {
            $target = $this->target($target);
            if (! array_key_exists($target, self::PLATFORMS)) {
                continue;
            }

            $controls[] = [
                'target' => $target,
                'label' => $this->label($target),
                'url' => $this->url($target, $permalink, $title),
            ];
        }

        return $controls;
    }

    public function recordShareClick(
        int $postId,
        string $target,
        string $visitorKey,
        string $ipAddress,
        string $userAgent,
        bool $consented,
        DateTimeImmutable $occurredAt,
    ): ?ReadingEvent {
        if (! $this->settings->current()->logClicks) {
            return null;
        }

        return $this->analytics->recordShareClick(
            $postId,
            $visitorKey,
            $ipAddress,
            $userAgent,
            $this->target($target),
            $consented,
            $occurredAt,
        );
    }

    private function url(string $target, string $permalink, string $title): string
    {
        $template = self::PLATFORMS[$target];
        if ($template === null) {
            return $permalink;
        }

        return strtr($template, [
            '{url}' => rawurlencode($permalink),
            '{title}' => rawurlencode($title),
        ]);
    }

    private function target(string $target): string
    {
        $target = strtolower(trim($target));
        $target = preg_replace('/[^a-z0-9_-]+/', '-', $target) ?? '';

        return trim($target, '-') ?: 'copy_link';
    }

    private function label(string $target): string
    {
        return match ($target) {
            'x' => __('X', 'corex'),
            'facebook' => __('Facebook', 'corex'),
            'linkedin' => __('LinkedIn', 'corex'),
            default => __('Copy link', 'corex'),
        };
    }
}
