<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Catalog;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowRepository;
use Corex\Forms\Form;
use Corex\Forms\FormRegistry;
use Throwable;

/**
 * Every form CoreX knows about, from every source, in one list.
 *
 * Before this existed each screen derived its own list from the flow table alone, so a form
 * registered in code through {@see FormRegistry} — the documented way to declare one — was invisible
 * unless the site added a `corex_submission_filter_options` hook of its own. Discovery is the
 * framework's job, not the site's.
 *
 * WordPress-free by construction: flows and code forms are already pure domain objects, and the two
 * things that genuinely need the database (submission counts, third-party entries) arrive through
 * injected seams. That keeps the merge — which is where the interesting rules live — unit-testable.
 */
final class FormCatalog
{
    /** @var list<FormCatalogProvider> */
    private array $providers = [];

    /**
     * The resolved catalog, and the registration state it was resolved from.
     *
     * Building the catalog reads the flow table, and the screens that show one also ask for its
     * shadowed entries and look forms up by slug — so without this a single render repeated the
     * same query several times. The signature guards the other direction: this service is a
     * container singleton, so a form registered on a later hook than the first read would
     * otherwise be permanently invisible.
     *
     * @var array{0:list<FormCatalogEntry>,1:list<FormCatalogEntry>}|null
     */
    private ?array $resolved = null;

    private string $signature = '';

    public function __construct(
        private readonly FlowRepository $flows,
        private readonly FormRegistry $forms,
        private readonly ?SubmissionCounts $counts = null,
    ) {
    }

    public function registerProvider(FormCatalogProvider $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * The catalog: one entry per slug, richest source winning, sorted by label.
     *
     * @return list<FormCatalogEntry>
     */
    public function all(): array
    {
        return $this->resolve()[0];
    }

    /**
     * The entries a higher-precedence source displaced.
     *
     * Kept rather than discarded because a slug collision is usually a mistake somebody wants to
     * find out about, and diagnostics is the honest place to say so — not a duplicate row.
     *
     * @return list<FormCatalogEntry>
     */
    public function shadowed(): array
    {
        return $this->resolve()[1];
    }

    /** @return array{0:list<FormCatalogEntry>,1:list<FormCatalogEntry>} */
    private function resolve(): array
    {
        $signature = $this->registrationSignature();

        if ($this->resolved !== null && $this->signature === $signature) {
            return $this->resolved;
        }

        [$entries, $shadowed] = $this->winners($this->collect());

        usort($entries, static fn (FormCatalogEntry $a, FormCatalogEntry $b): int => strcasecmp($a->label, $b->label));

        $this->signature = $signature;

        return $this->resolved = [array_values($entries), $shadowed];
    }

    /**
     * What the catalog was built from, cheaply enough to check on every read.
     *
     * Code forms and providers are the parts that can appear part-way through a request, and both
     * only ever grow, so their counts are enough to notice a late registration. The slugs are
     * included too, because a registry rebuilt with the same number of different forms is a
     * different catalog.
     */
    private function registrationSignature(): string
    {
        $slugs = array_map(static fn (Form $form): string => $form->slug, $this->forms->all());

        return count($this->providers) . '|' . implode(',', $slugs);
    }

    public function find(string $slug): ?FormCatalogEntry
    {
        foreach ($this->all() as $entry) {
            if ($entry->slug === $slug) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Every candidate entry from every source, duplicates included.
     *
     * @return list<FormCatalogEntry>
     */
    private function collect(): array
    {
        return [...$this->flowEntries(), ...$this->codeFormEntries(), ...$this->providerEntries()];
    }

    /**
     * Resolve slug collisions, and attach counts once the winners are known.
     *
     * @param  list<FormCatalogEntry> $candidates
     * @return array{0:list<FormCatalogEntry>,1:list<FormCatalogEntry>}
     */
    private function winners(array $candidates): array
    {
        /** @var array<string,FormCatalogEntry> $best */
        $best     = [];
        $shadowed = [];

        foreach ($candidates as $entry) {
            $incumbent = $best[$entry->slug] ?? null;

            if ($incumbent === null) {
                $best[$entry->slug] = $entry;

                continue;
            }

            // Ties keep the incumbent: two entries from the same source are the same registration
            // read twice, and re-ordering them would make the catalog depend on iteration order.
            if (FormSource::precedence($entry->source) > FormSource::precedence($incumbent->source)) {
                $best[$entry->slug] = $entry;
                $shadowed[]         = $incumbent;

                continue;
            }

            $shadowed[] = $entry;
        }

        $counts = $this->submissionCounts();

        if ($counts === null) {
            return [array_values($best), $shadowed];
        }

        $withCounts = array_map(
            static fn (FormCatalogEntry $entry): FormCatalogEntry => $entry->withSubmissionCount($counts[$entry->slug] ?? 0),
            array_values($best),
        );

        return [$withCounts, $shadowed];
    }

    /** @return array<string,int>|null */
    private function submissionCounts(): ?array
    {
        if ($this->counts === null) {
            return null;
        }

        try {
            return $this->counts->perFormSlug();
        } catch (Throwable) {
            // An unreachable counter must not take the whole catalog down with it; every entry
            // then reports its count as unavailable, which is what "we could not measure" means.
            return null;
        }
    }

    /** @return list<FormCatalogEntry> */
    private function flowEntries(): array
    {
        try {
            $flows = $this->flows->all();
        } catch (Throwable) {
            return [];
        }

        return array_map(static fn (Flow $flow): FormCatalogEntry => FormCatalogEntry::create(
            slug: $flow->slug,
            label: $flow->name,
            source: FormSource::VISUAL_FLOW,
            flowId: $flow->id,
            active: $flow->state === Flow::STATE_PUBLISHED,
            editableInBuilder: true,
            capability: CorexAbility::MANAGE_FORMS,
        ), $flows);
    }

    /** @return list<FormCatalogEntry> */
    private function codeFormEntries(): array
    {
        $entries = [];

        foreach ($this->forms->all() as $form) {
            if ($form->slug === '') {
                continue;
            }

            $entries[] = FormCatalogEntry::create(
                slug: $form->slug,
                label: $form->label(),
                source: FormSource::CODE_FORM,
                fields: self::fieldsOf($form),
                editableInBuilder: false,
                capability: CorexAbility::MANAGE_FORMS,
            );
        }

        return $entries;
    }

    /** @return list<FormCatalogEntry> */
    private function providerEntries(): array
    {
        $entries = [];

        foreach ($this->providers as $provider) {
            try {
                $raw = $provider->formCatalogEntries();
            } catch (Throwable) {
                // A third-party provider is not allowed to break the screen it appears on.
                continue;
            }

            foreach ($raw as $candidate) {
                $entry = self::externalEntry($candidate);

                if ($entry !== null) {
                    $entries[] = $entry;
                }
            }
        }

        return $entries;
    }

    /**
     * Force an untrusted provider row into the shape every consumer relies on, or reject it.
     *
     * An entry with no usable slug cannot be filtered on, linked to, or counted, so it would render
     * as a control that does nothing. Dropping it is the only truthful option.
     *
     * @param mixed $candidate
     */
    private static function externalEntry(mixed $candidate): ?FormCatalogEntry
    {
        if (! is_array($candidate)) {
            return null;
        }

        $slug = self::slugify((string) ($candidate['slug'] ?? ''));

        if ($slug === '') {
            return null;
        }

        return FormCatalogEntry::create(
            slug: $slug,
            label: trim((string) ($candidate['label'] ?? '')),
            source: FormSource::EXTERNAL,
            fields: self::externalFields($candidate['fields'] ?? []),
            declaredFieldCount: isset($candidate['field_count']) ? max(0, (int) $candidate['field_count']) : null,
            active: (bool) ($candidate['active'] ?? true),
            editableInBuilder: false,
            capability: CorexAbility::MANAGE_FORMS,
        );
    }

    /**
     * @param  array<string,array{type?:string,rules?:list<string>,label?:string}> $definitions
     * @return list<array{key:string,label:string,type:string,rules:list<string>}>
     */
    private static function fieldsOf(Form $form): array
    {
        $fields = [];

        foreach ($form->fields() as $key => $definition) {
            $key = (string) $key;

            $fields[] = [
                'key'   => $key,
                'label' => (string) ($definition['label'] ?? '') !== '' ? (string) $definition['label'] : $key,
                'type'  => (string) ($definition['type'] ?? 'text'),
                'rules' => array_values(array_map('strval', (array) ($definition['rules'] ?? []))),
            ];
        }

        return $fields;
    }

    /**
     * @param  mixed $fields
     * @return list<array{key:string,label:string,type:string,rules:list<string>}>
     */
    private static function externalFields(mixed $fields): array
    {
        if (! is_array($fields)) {
            return [];
        }

        $clean = [];

        foreach ($fields as $key => $field) {
            $field = is_array($field) ? $field : [];
            $name  = self::slugify((string) ($field['key'] ?? $key));

            if ($name === '') {
                continue;
            }

            $clean[] = [
                'key'   => $name,
                'label' => trim((string) ($field['label'] ?? '')) !== '' ? trim((string) $field['label']) : $name,
                'type'  => self::slugify((string) ($field['type'] ?? 'text')) ?: 'text',
                'rules' => array_values(array_map('strval', (array) ($field['rules'] ?? []))),
            ];
        }

        return $clean;
    }

    /**
     * The pure equivalent of `sanitize_key`, so the merge stays runnable without WordPress.
     */
    private static function slugify(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($value)));
    }
}
