<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Revision-oriented email layout catalog.
 */
final class EmailLayoutRepository
{
    private const TYPE = 'email_layout';

    /** @var array<string,array{regions:array<string,string>,dependency:?string}> */
    private const DEFAULTS = [
        'transactional' => [
            'regions'    => [
                'header' => '{{> header }}', 'accent' => '', 'body' => '{{ content }}',
                'button' => '', 'footer' => '{{> footer }}',
            ],
            'dependency' => null,
        ],
        'minimal' => [
            'regions'    => [
                'header' => '', 'accent' => '', 'body' => '{{ content }}', 'button' => '', 'footer' => '',
            ],
            'dependency' => null,
        ],
        'newsletter' => [
            'regions'    => [
                'header' => '{{> header }}', 'accent' => '', 'body' => '{{ content }}',
                'button' => '', 'footer' => '{{> unsubscribe }}{{> preferences }}{{> footer }}',
            ],
            'dependency' => null,
        ],
        'woocommerce' => [
            'regions'    => [
                'header' => '{{> header }}', 'accent' => '', 'body' => '{{ content }}',
                'button' => '', 'footer' => '{{> footer }}',
            ],
            'dependency' => 'woocommerce',
        ],
    ];

    public function __construct(private readonly EmailStudioStore $store)
    {
    }

    public function installDefaults(bool $wooAvailable, int $actorId, DateTimeImmutable $now): void
    {
        foreach (self::DEFAULTS as $slug => $definition) {
            if ($definition['dependency'] === 'woocommerce' && ! $wooAvailable) {
                continue;
            }

            $existing = $this->find($slug);
            if ($existing !== null) {
                if ($this->requiresSchemaUpgrade($slug)) {
                    $this->save(new EmailLayout(
                        id: 0,
                        slug: $existing->slug,
                        name: $existing->name,
                        version: $existing->version,
                        regions: $existing->regions,
                        dependency: $existing->dependency,
                        createdBy: $actorId,
                        createdAt: $now,
                        status: $existing->status,
                    ));
                }
                continue;
            }

            $this->store->create(self::TYPE, $slug, $this->defaultName($slug), 0, [
                'asset_slug' => $slug,
                'schema_version' => 2,
                'status'     => 'active',
                'version'    => 1,
                'regions'    => $definition['regions'],
                ...$this->regionPayload($definition['regions']),
                'dependency' => $definition['dependency'],
                'created_by' => $actorId,
                'created_at' => $now->format(DATE_ATOM),
                'updated_at' => $now->format(DATE_ATOM),
            ]);
        }
    }

    private function defaultName(string $slug): string
    {
        return match ($slug) {
            'transactional' => __('Transactional', 'corex'),
            'minimal'       => __('Minimal', 'corex'),
            'newsletter'    => __('Newsletter', 'corex'),
            'woocommerce'   => __('WooCommerce', 'corex'),
            default         => throw new \DomainException(__('Unknown default email layout.', 'corex')),
        };
    }

    /** @return list<EmailLayout> */
    public function all(): array
    {
        $latest = [];
        foreach ($this->store->all(self::TYPE) as $record) {
            $layout = $this->layout($record);
            if (! isset($latest[$layout->slug]) || $latest[$layout->slug]->version < $layout->version) {
                $latest[$layout->slug] = $layout;
            }
        }

        return array_values($latest);
    }

    public function find(string $slug): ?EmailLayout
    {
        $versions = $this->versions($slug);

        return $versions === [] ? null : $versions[array_key_last($versions)];
    }

    public function findVersion(int $layoutId, int $version): ?EmailLayout
    {
        foreach ($this->store->all(self::TYPE) as $record) {
            $layout = $this->layout($record);
            if ($layout->id === $layoutId && $layout->version === $version) {
                return $layout;
            }
        }

        return null;
    }

    public function save(EmailLayout $layout): EmailLayout
    {
        $version = count($this->versions($layout->slug)) + 1;
        $id = $this->store->create(self::TYPE, sprintf('%s-v%d', $layout->slug, $version), $layout->name, 0, [
            'asset_slug' => $layout->slug,
            'schema_version' => 2,
            'status'     => $layout->status,
            'version'    => $version,
            'regions'    => $layout->regions,
            ...$this->regionPayload($layout->regions),
            'dependency' => $layout->dependency,
            'created_by' => $layout->createdBy,
            'created_at' => $layout->createdAt->format(DATE_ATOM),
            'updated_at' => $layout->createdAt->format(DATE_ATOM),
        ]);
        $record = $this->store->find($id);

        return $this->layout($record ?? throw new \DomainException(__('Email layout could not be stored.', 'corex')));
    }

    /** @return list<EmailLayout> */
    public function versions(string $slug): array
    {
        $records = array_filter(
            $this->store->all(self::TYPE),
            static fn (array $record): bool => ($record['payload']['asset_slug'] ?? $record['slug']) === $slug,
        );
        $versions = array_map($this->layout(...), array_values($records));
        usort($versions, static fn (EmailLayout $left, EmailLayout $right): int => $left->version <=> $right->version);

        return $versions;
    }

    /** @param array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>} $record */
    private function layout(array $record): EmailLayout
    {
        $payload = $record['payload'];
        $regions = $this->regionsFromPayload($payload);
        if ($regions === []) {
            foreach ((array) ($payload['regions'] ?? []) as $name => $value) {
                if (is_string($name) && is_string($value)) {
                    $regions[$name] = $value;
                }
            }
        }

        return new EmailLayout(
            id: $record['id'],
            slug: (string) ($payload['asset_slug'] ?? $record['slug']),
            name: $record['name'],
            version: (int) ($payload['version'] ?? 0),
            regions: $regions,
            dependency: is_string($payload['dependency'] ?? null) ? $payload['dependency'] : null,
            createdBy: (int) ($payload['created_by'] ?? 0),
            createdAt: new DateTimeImmutable((string) ($payload['updated_at'] ?? $payload['created_at'] ?? '')),
            status: (string) ($payload['status'] ?? 'active'),
        );
    }

    /**
     * @param array<string,string> $regions
     *
     * @return array<string,array<string,string>>
     */
    private function regionPayload(array $regions): array
    {
        return [
            'header_json' => ['html' => (string) ($regions['header'] ?? '')],
            'accent_json' => ['color' => (string) ($regions['accent'] ?? '')],
            'body_json'   => ['html' => (string) ($regions['body'] ?? '')],
            'button_json' => ['html' => (string) ($regions['button'] ?? '')],
            'footer_json' => ['html' => (string) ($regions['footer'] ?? '')],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     *
     * @return array<string,string>
     */
    private function regionsFromPayload(array $payload): array
    {
        $regions = [];
        foreach (['header', 'accent', 'body', 'button', 'footer'] as $name) {
            $region = $payload[$name . '_json'] ?? null;
            $key = $name === 'accent' ? 'color' : 'html';
            if (is_array($region) && is_string($region[$key] ?? null)) {
                $regions[$name] = $region[$key];
            }
        }

        return $regions;
    }

    private function requiresSchemaUpgrade(string $slug): bool
    {
        $records = array_values(array_filter(
            $this->store->all(self::TYPE),
            static fn (array $record): bool => ($record['payload']['asset_slug'] ?? $record['slug']) === $slug,
        ));
        $latest = $records === [] ? null : $records[array_key_last($records)];

        return (int) ($latest['payload']['schema_version'] ?? 0) < 2;
    }
}
