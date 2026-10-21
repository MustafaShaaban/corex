<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\FlowRepository;
use Corex\Forms\Flow\FlowVersion;
use Corex\Mail\SubmissionEmailRecipients;

/**
 * Works out again who one of a submission's flow emails is addressed to (spec 112, FR-032a): the
 * binding of the flow version the submission was made with, resolved against its answers and
 * routing as the email stage resolved it when it sent. For an email sent before its recipients
 * were recorded; the inbox sends it again only if its attempt confirms the address.
 */
final readonly class FlowSubmissionEmailRecipients implements SubmissionEmailRecipients
{
    public function __construct(
        private SubmissionRepository $submissions,
        private FlowRepository $flows,
        private FlowEmailAddressResolver $addresses,
    ) {
    }

    public function resolve(int $submissionId, string $event): ?array
    {
        $source  = $this->submissions->emailSource($submissionId);
        $flow    = $source === null ? null : $this->flows->find($source['flow_id']);
        $binding = $flow === null ? null : $this->binding($source['flow_id'], $source['version_id'], $event);
        if ($binding === null) {
            return null;
        }

        return $this->addresses->recipients((string) ($binding['recipient'] ?? ''), [
            'submission' => $source['values'],
            'flow' => ['id' => $flow->id, 'slug' => $flow->slug, 'owner_id' => $flow->ownerId],
            'routing' => $source['routing'],
        ]);
    }

    /**
     * The enabled template binding for this event in the version the submission was made with.
     *
     * @return array<string,mixed>|null
     */
    private function binding(int $flowId, int $versionId, string $event): ?array
    {
        $versions = array_filter($this->flows->versions($flowId), static fn (FlowVersion $version): bool => $version->id === $versionId);
        $version  = reset($versions);
        if (! $version instanceof FlowVersion) {
            return null;
        }

        foreach ($version->configuration->emailRoutes as $binding) {
            if (is_array($binding) && ($binding['enabled'] ?? false) && ($binding['event'] ?? '') === $event
                && (int) ($binding['template_id'] ?? 0) > 0) {
                return $binding;
            }
        }

        return null;
    }
}
