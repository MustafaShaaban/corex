<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Models\Model;
use Corex\Repositories\PostRepository;

/**
 * Persists submissions through the data layer (Principle III: the repository is the
 * only layer that touches the data source). Stores a private `corex_submission` post
 * plus the form slug and each validated value as `corex_field_*` meta — queryable by slug.
 */
final class SubmissionRepository extends PostRepository implements SubmissionStore
{
    protected function model(): string
    {
        return Submission::class;
    }

    /**
     * The {@see SubmissionStore} seam (spec 045): persist and return the id. The default
     * post-meta driver.
     *
     * @param array<string,mixed> $values
     */
    public function save(string $slug, array $values): int
    {
        return $this->store($slug, $values)->id();
    }

    /**
     * @param array<string,mixed> $values validated values, keyed by canonical field name
     */
    public function store(string $slug, array $values): Model
    {
        $submission = $this->create([
            'title'  => sprintf('%s — %s', $slug, current_time('mysql')),
            'status' => 'private',
        ]);

        $this->fields->set($submission->id(), 'corex_form_slug', $slug);

        foreach ($values as $name => $value) {
            $this->fields->set($submission->id(), 'corex_field_' . $name, $value);
        }

        return $submission;
    }
}
