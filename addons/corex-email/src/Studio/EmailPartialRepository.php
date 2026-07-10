<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Append-only repository for reusable email partials.
 */
final class EmailPartialRepository
{
    private const TYPE = 'email_partial';

    /** @var array<string,array{kind:string}> */
    private const DEFAULTS = [
        'header'      => ['kind' => 'header'],
        'footer'      => ['kind' => 'footer'],
        'unsubscribe' => ['kind' => 'unsubscribe'],
        'preferences' => ['kind' => 'preferences'],
        'privacy'     => ['kind' => 'privacy'],
    ];

    public function __construct(private readonly EmailStudioStore $store)
    {
    }

    public function save(EmailPartial $partial): EmailPartial
    {
        $version = count($this->versions($partial->slug)) + 1;
        $id      = $this->store->create(self::TYPE, sprintf('%s-v%d', $partial->slug, $version), $partial->name, 0, [
            'asset_slug' => $partial->slug,
            'kind'       => $partial->kind,
            'html_body'  => $partial->htmlBody,
            'plain_text' => $partial->plainText,
            'status'     => $partial->status,
            'version'    => $version,
            'created_by' => $partial->createdBy,
            'created_at' => $partial->createdAt->format(DATE_ATOM),
        ]);

        $record = $this->store->find($id);

        return $this->partial($record ?? throw new \DomainException(__('Email partial could not be stored.', 'corex')));
    }

    public function installDefaults(int $actorId, DateTimeImmutable $now): void
    {
        foreach (self::DEFAULTS as $slug => $definition) {
            if ($this->find($slug) !== null) {
                continue;
            }

            $content = $this->defaultContent($slug);
            $this->save(new EmailPartial(
                id: 0,
                slug: $slug,
                name: $content['name'],
                kind: $definition['kind'],
                htmlBody: $content['html'],
                plainText: $content['plain'],
                status: 'active',
                version: 1,
                createdBy: $actorId,
                createdAt: $now,
            ));
        }
    }

    /** @return array{name:string,html:string,plain:string} */
    private function defaultContent(string $slug): array
    {
        return match ($slug) {
            'header' => [
                'name'  => __('Header', 'corex'),
                'html'  => '<strong>{{ site.name }}</strong>',
                'plain' => '{{ site.name }}',
            ],
            'footer' => [
                'name'  => __('Footer', 'corex'),
                'html'  => '<p>{{ site.name }}</p>',
                'plain' => '{{ site.name }}',
            ],
            'unsubscribe' => [
                'name'  => __('Unsubscribe', 'corex'),
                'html'  => '<p><a href="{{ links.unsubscribe }}">' . esc_html__('Unsubscribe', 'corex') . '</a></p>',
                'plain' => __('Unsubscribe: {{ links.unsubscribe }}', 'corex'),
            ],
            'preferences' => [
                'name'  => __('Preferences', 'corex'),
                'html'  => '<p><a href="{{ links.preferences }}">' . esc_html__('Preferences', 'corex') . '</a></p>',
                'plain' => __('Preferences: {{ links.preferences }}', 'corex'),
            ],
            'privacy' => [
                'name'  => __('Privacy', 'corex'),
                'html'  => '<p><a href="{{ links.privacy }}">' . esc_html__('Privacy', 'corex') . '</a></p>',
                'plain' => __('Privacy: {{ links.privacy }}', 'corex'),
            ],
            default => throw new \DomainException(__('Unknown default email partial.', 'corex')),
        };
    }

    public function find(string $slug): ?EmailPartial
    {
        $versions = $this->versions($slug);

        return $versions === [] ? null : $versions[array_key_last($versions)];
    }

    /** @return list<EmailPartial> */
    public function all(): array
    {
        $latest = [];
        foreach ($this->store->all(self::TYPE) as $record) {
            $partial = $this->partial($record);
            if (! isset($latest[$partial->slug]) || $latest[$partial->slug]->version < $partial->version) {
                $latest[$partial->slug] = $partial;
            }
        }

        return array_values($latest);
    }

    /** @return list<EmailPartial> */
    public function versions(string $slug): array
    {
        $records = array_filter(
            $this->store->all(self::TYPE),
            static fn (array $record): bool => ($record['payload']['asset_slug'] ?? null) === $slug,
        );
        $partials = array_map($this->partial(...), array_values($records));
        usort($partials, static fn (EmailPartial $left, EmailPartial $right): int => $left->version <=> $right->version);

        return $partials;
    }

    /** @param array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>} $record */
    private function partial(array $record): EmailPartial
    {
        $payload = $record['payload'];

        return new EmailPartial(
            id: $record['id'],
            slug: (string) ($payload['asset_slug'] ?? ''),
            name: $record['name'],
            kind: (string) ($payload['kind'] ?? ''),
            htmlBody: (string) ($payload['html_body'] ?? ''),
            plainText: (string) ($payload['plain_text'] ?? ''),
            status: (string) ($payload['status'] ?? ''),
            version: (int) ($payload['version'] ?? 0),
            createdBy: (int) ($payload['created_by'] ?? 0),
            createdAt: new DateTimeImmutable((string) ($payload['created_at'] ?? '')),
        );
    }
}
