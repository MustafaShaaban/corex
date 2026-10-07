<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Forms;

defined('ABSPATH') || exit;

use Corex\Config\Submissions\SubmissionQuestions;
use Corex\Container\ContainerInterface;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowService;
use Corex\Forms\Schema\FieldSchema;
use Corex\Forms\Submission\FormSubmissionService;
use Throwable;

/**
 * Reads a form's questions from corex-forms: a form defined in code, or a flow built in the admin.
 *
 * Forms is an optional plugin (Principle IX), so it is resolved when asked and inside a
 * try/catch, as {@see FlowFilterOptions} does. Without it, or for a form that no longer exists,
 * the answer is empty and an export heads its answers by their stored keys.
 */
final class FormQuestions implements SubmissionQuestions
{
    /** A step divides a flow into pages. It asks nothing. */
    private const NOT_A_QUESTION = 'step';

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function for(string $form): array
    {
        try {
            $fromCode = $this->container->make(FormSubmissionService::class)->schemaFor($form);

            return $fromCode !== [] ? $this->fromSchema($fromCode) : $this->fromFlow($form);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param array<string,FieldSchema> $schema
     *
     * @return list<array{key:string,label:string,type:string}>
     */
    private function fromSchema(array $schema): array
    {
        return array_values(array_map(
            static fn (FieldSchema $field): array => [
                'key' => $field->name,
                'label' => $field->label,
                'type' => $field->type,
            ],
            $schema,
        ));
    }

    /**
     * @return list<array{key:string,label:string,type:string}>
     */
    private function fromFlow(string $slug): array
    {
        $flows = $this->container->make(FlowService::class);

        foreach ($flows->all() as $flow) {
            if ($flow->slug === $slug) {
                return $this->questionsOf($flows, $flow);
            }
        }

        return [];
    }

    /**
     * The questions as visitors are answering them now; a flow never published has only a draft.
     *
     * @return list<array{key:string,label:string,type:string}>
     */
    private function questionsOf(FlowService $flows, Flow $flow): array
    {
        $version = $flow->state === Flow::STATE_PUBLISHED && $flow->publishedVersion > 0
            ? $flows->publishedVersion($flow)
            : $flows->currentVersion($flow);

        $questions = [];
        foreach ($version->configuration->schema as $field) {
            $key  = (string) ($field['key'] ?? '');
            $type = (string) ($field['type'] ?? 'text');

            if ($key !== '' && $type !== self::NOT_A_QUESTION) {
                $questions[] = ['key' => $key, 'label' => (string) ($field['label'] ?? $key), 'type' => $type];
            }
        }

        return $questions;
    }
}
