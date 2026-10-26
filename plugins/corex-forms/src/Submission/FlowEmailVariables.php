<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\FlowConfiguration;

/**
 * What a flow's emails supply to an Email Studio template, and which of a template's variables
 * they do not (spec 120, story 5): the site's own, the form's title, every answer, a link to the
 * submission, and each of this flow's fields by its key.
 */
final class FlowEmailVariables
{
    /** Supplied to every email, whatever sends it. */
    private const EVERY = ['site.name', 'site.url', 'links.privacy', 'recipient.email'];

    /** Supplied by every email of a flow. */
    private const FLOW = ['form.title', 'form.answers', 'links.submission'];

    /** Supplied only to the alert that a flow's email failed. */
    private const FAILURE = ['failure.message'];

    /**
     * The variables a template uses that this flow's email does not supply.
     *
     * @param list<string> $used The template's variables.
     *
     * @return list<string>
     */
    public static function missing(array $used, FlowConfiguration $configuration, string $event): array
    {
        $supplied = [
            ...self::EVERY,
            ...self::FLOW,
            ...($event === 'admin_failure' ? self::FAILURE : []),
            ...array_map(static fn (string $key): string => 'submission.' . $key, self::fieldKeys($configuration)),
        ];

        return array_values(array_diff(array_unique($used), $supplied));
    }

    /**
     * What kind of email a binding sends, as the frame's footer says it.
     */
    public static function kindOf(string $event): string
    {
        return $event === 'submitter_confirmation' ? 'confirmation' : 'notification';
    }

    /** @return list<string> */
    public static function fieldKeys(FlowConfiguration $configuration): array
    {
        $keys = [];
        foreach ($configuration->schema as $field) {
            if (is_array($field) && is_string($field['key'] ?? null) && $field['key'] !== '') {
                $keys[] = $field['key'];
            }
        }

        return $keys;
    }

    /**
     * Every answer of a submission with its field's label, in the form's order; a file's answer
     * links to the stored submission, where it is opened after signing in.
     *
     * @param array<string,mixed> $values
     *
     * @return list<array{label:string,value:string,url?:string}>
     */
    public static function answers(FlowConfiguration $configuration, array $values, string $submissionUrl): array
    {
        $answers = [];
        foreach ($configuration->schema as $field) {
            $key = is_array($field) ? (string) ($field['key'] ?? '') : '';
            if ($key === '' || ! array_key_exists($key, $values)) {
                continue;
            }
            $value = $values[$key];
            $answer = [
                'label' => trim((string) ($field['label'] ?? '')) !== '' ? (string) $field['label'] : $key,
                'value' => is_array($value) ? implode(', ', array_map('strval', array_filter($value, 'is_scalar'))) : (string) (is_scalar($value) ? $value : ''),
            ];
            if (($field['type'] ?? '') === 'file' && $submissionUrl !== '') {
                $answer['url'] = $submissionUrl;
            }
            $answers[] = $answer;
        }

        return $answers;
    }
}
