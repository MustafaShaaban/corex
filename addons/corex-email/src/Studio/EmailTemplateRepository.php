<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use Corex\Support\Uuid;
use DateTimeImmutable;
use DomainException;
use JsonException;

/**
 * Stores template metadata while preserving each content revision.
 */
final class EmailTemplateRepository
{
    private const TYPE_TEMPLATE = 'email_template';
    private const TYPE_VERSION  = 'email_template_version';

    public function __construct(private readonly EmailStudioStore $store)
    {
    }

    public function create(string $slug, string $name, int $actorId, DateTimeImmutable $now): EmailTemplate
    {
        if ($this->store->findBySlug(self::TYPE_TEMPLATE, $slug) !== null) {
            throw new DomainException(__('An email template already uses this slug.', 'corex'));
        }

        $payload = [
            'uuid'           => Uuid::v4(),
            'status'         => EmailTemplate::STATUS_DRAFT,
            'draft_version'  => 0,
            'active_version' => 0,
            'updated_by'     => $actorId,
            'updated_at'     => $now->format(DATE_ATOM),
        ];
        $id = $this->store->create(self::TYPE_TEMPLATE, $slug, $name, 0, $payload);

        return $this->template($this->requireRecord($id, self::TYPE_TEMPLATE));
    }

    public function saveDraft(EmailTemplateVersion $draft): EmailTemplateVersion
    {
        $templateId     = $draft->templateId;
        $actorId        = $draft->createdBy;
        $now            = $draft->createdAt;
        $templateRecord = $this->requireRecord($templateId, self::TYPE_TEMPLATE);
        $template       = $this->template($templateRecord);
        $versionNumber = count($this->versions($templateId)) + 1;
        $content = [
            'subject'        => $draft->subject,
            'fromName'       => $draft->fromName,
            'fromAddress'    => $draft->fromAddress,
            'htmlBody'       => $draft->htmlBody,
            'plainText'      => $draft->plainText,
            'plainTextMode'  => $draft->plainTextMode,
            'layoutId'       => $draft->layoutId,
            'layoutVersion'  => $draft->layoutVersion,
            'variableKeys'   => $draft->variableKeys,
        ];
        $payload = [
            ...$content,
            'version_number' => $versionNumber,
            'created_by'     => $actorId,
            'created_at'     => $now->format(DATE_ATOM),
            'checksum'       => $this->checksum($content),
        ];
        $id = $this->store->create(
            self::TYPE_VERSION,
            sprintf('%s-v%d', $template->slug, $versionNumber),
            sprintf('%s v%d', $template->name, $versionNumber),
            $templateId,
            $payload,
        );

        $templatePayload                  = $templateRecord['payload'];
        $templatePayload['draft_version'] = $versionNumber;
        $templatePayload['updated_by']    = $actorId;
        $templatePayload['updated_at']    = $now->format(DATE_ATOM);
        if (! $this->store->update($templateId, $template->name, $templatePayload)) {
            throw new DomainException(__('Email template metadata could not be updated.', 'corex'));
        }

        return $this->version($this->requireRecord($id, self::TYPE_VERSION));
    }

    public function activate(int $templateId, int $versionNumber, int $actorId, DateTimeImmutable $now): EmailTemplate
    {
        $record   = $this->requireRecord($templateId, self::TYPE_TEMPLATE);
        $versions = array_filter(
            $this->versions($templateId),
            static fn (EmailTemplateVersion $version): bool => $version->versionNumber === $versionNumber,
        );

        if ($versions === []) {
            throw new DomainException(__('The requested template version does not belong to this template.', 'corex'));
        }

        $payload                   = $record['payload'];
        $payload['status']         = EmailTemplate::STATUS_ACTIVE;
        $payload['active_version'] = $versionNumber;
        $payload['updated_by']     = $actorId;
        $payload['updated_at']     = $now->format(DATE_ATOM);
        if (! $this->store->update($templateId, $record['name'], $payload)) {
            throw new DomainException(__('Email template could not be activated.', 'corex'));
        }

        return $this->template($this->requireRecord($templateId, self::TYPE_TEMPLATE));
    }

    public function find(int $templateId): ?EmailTemplate
    {
        $record = $this->store->find($templateId);

        return $record === null || $record['type'] !== self::TYPE_TEMPLATE ? null : $this->template($record);
    }

    public function findBySlug(string $slug): ?EmailTemplate
    {
        $record = $this->store->findBySlug(self::TYPE_TEMPLATE, $slug);

        return $record === null ? null : $this->template($record);
    }

    /** @return list<EmailTemplate> */
    public function all(): array
    {
        return array_map($this->template(...), $this->store->all(self::TYPE_TEMPLATE));
    }

    public function findVersion(int $templateId, int $versionNumber): ?EmailTemplateVersion
    {
        foreach ($this->versions($templateId) as $version) {
            if ($version->versionNumber === $versionNumber) {
                return $version;
            }
        }

        return null;
    }

    /** @return list<EmailTemplateVersion> */
    public function versions(int $templateId): array
    {
        $versions = array_map($this->version(...), $this->store->all(self::TYPE_VERSION, $templateId));
        usort($versions, static fn (EmailTemplateVersion $left, EmailTemplateVersion $right): int => $left->versionNumber <=> $right->versionNumber);

        return $versions;
    }

    /** @param array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>} $record */
    private function template(array $record): EmailTemplate
    {
        $payload = $record['payload'];

        return new EmailTemplate(
            id: $record['id'],
            uuid: (string) ($payload['uuid'] ?? ''),
            slug: $record['slug'],
            name: $record['name'],
            status: (string) ($payload['status'] ?? ''),
            draftVersion: (int) ($payload['draft_version'] ?? 0),
            activeVersion: (int) ($payload['active_version'] ?? 0),
            updatedBy: (int) ($payload['updated_by'] ?? 0),
            updatedAt: new DateTimeImmutable((string) ($payload['updated_at'] ?? '')),
        );
    }

    /** @param array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>} $record */
    private function version(array $record): EmailTemplateVersion
    {
        $payload = $record['payload'];

        return new EmailTemplateVersion(
            id: $record['id'],
            templateId: $record['parentId'],
            versionNumber: (int) ($payload['version_number'] ?? 0),
            subject: (string) ($payload['subject'] ?? ''),
            fromName: (string) ($payload['fromName'] ?? ''),
            fromAddress: (string) ($payload['fromAddress'] ?? ''),
            htmlBody: (string) ($payload['htmlBody'] ?? ''),
            plainText: (string) ($payload['plainText'] ?? ''),
            plainTextMode: (string) ($payload['plainTextMode'] ?? ''),
            layoutId: (int) ($payload['layoutId'] ?? 0),
            layoutVersion: (int) ($payload['layoutVersion'] ?? 0),
            variableKeys: array_values(array_filter((array) ($payload['variableKeys'] ?? []), 'is_string')),
            createdBy: (int) ($payload['created_by'] ?? 0),
            createdAt: new DateTimeImmutable((string) ($payload['created_at'] ?? '')),
            checksum: (string) ($payload['checksum'] ?? ''),
        );
    }

    /** @return array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>} */
    private function requireRecord(int $id, string $type): array
    {
        $record = $this->store->find($id);
        if ($record === null || $record['type'] !== $type) {
            throw new DomainException(__('Email Studio record was not found.', 'corex'));
        }

        return $record;
    }

    /** @param array<string,mixed> $content */
    private function checksum(array $content): string
    {
        try {
            return hash('sha256', json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $exception) {
            throw new DomainException(__('Email template content could not be encoded.', 'corex'), previous: $exception);
        }
    }
}
