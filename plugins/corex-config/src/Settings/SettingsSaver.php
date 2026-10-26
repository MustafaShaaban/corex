<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

use Corex\Support\Config\Truthy;

/**
 * Saves one Settings page (spec 119, US-SET-01, US-SET-02, US-SET-15; spec 114 FR-028).
 *
 * Only the fields of the page that was posted are written, only when their section's add-on is
 * there and on, only when the value posted differs from the value the page drew, and never when
 * the site's `.env` or the network locks the field. A secret posted empty keeps its stored value;
 * a secret named for removal is deleted and the removal recorded. A value the field does not take
 * is refused and reported, and what was there stays.
 */
final class SettingsSaver
{
    /** The post field that names the secrets to remove. */
    public const REMOVE = 'corex_remove_secret';

    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly SettingsPages $pages,
        private readonly SettingsSectionStates $states,
        private readonly SettingsFieldValues $values,
        private readonly SettingsStore $store,
        private readonly SettingsActivity $activity,
        private readonly SettingsSanitizer $sanitizer,
    ) {
    }

    /**
     * @param array<string,mixed> $posted the post, unslashed
     *
     * @return SettingsSaveResult|null null when no page of that key may be saved
     */
    public function save(string $pageKey, array $posted, int $actorId): ?SettingsSaveResult
    {
        $page = $this->pages->find($pageKey);
        if ($page === null) {
            return null;
        }

        $toRemove = array_map('strval', array_filter((array) ($posted[self::REMOVE] ?? []), 'is_scalar'));
        $refused  = [];
        $removed  = [];

        foreach ($this->editableFields($page) as $key => $field) {
            if (($field['type'] ?? '') === 'password' && in_array($key, $toRemove, true)) {
                $removed = [...$removed, ...$this->remove($key, $field, $actorId)];

                continue;
            }

            $refusal = $this->saveField($key, $field, $posted);
            if ($refusal !== null) {
                $refused[] = $refusal;
            }
        }

        return new SettingsSaveResult($page->key, $refused, $removed);
    }

    /**
     * The fields of the page a save may write: in a section whose add-on is there and on, not a
     * read-out, and not locked by the site's `.env` or the network.
     *
     * @return array<string,array<string,mixed>> key => field
     */
    private function editableFields(SettingsPage $page): array
    {
        $sections = $this->registry->sections();
        $fields   = [];

        foreach ($page->sections as $sectionKey) {
            if (! isset($sections[$sectionKey]) || ! $this->states->editable($sectionKey)) {
                continue;
            }

            foreach ($sections[$sectionKey]['fields'] as $key => $field) {
                $type = (string) ($field['type'] ?? 'text');
                if ($type !== 'info' && ! $this->values->state((string) $key, $type)->locked()) {
                    $fields[(string) $key] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * @param array{label?:string} $field
     *
     * @return list<string> the key, when something was removed
     */
    private function remove(string $key, array $field, int $actorId): array
    {
        if ($this->store->get($key) === '') {
            return [];
        }

        $this->store->delete($key);
        $this->activity->secretRemoved($actorId, $key, (string) ($field['label'] ?? $key));

        return [$key];
    }

    /**
     * Writes one field when its value changed; answers the refusal when the value is not one the
     * field takes.
     *
     * @param array{type?:string,label?:string,options?:array<string,string>} $field
     * @param array<string,mixed>                                             $posted
     *
     * @return array{name:string,label:string,type:string,typed:string}|null
     */
    private function saveField(string $key, array $field, array $posted): ?array
    {
        $name  = str_replace('.', '_', $key);
        $type  = (string) ($field['type'] ?? 'text');
        $shown = $this->values->shown($key);
        if ($type === 'select') {
            // A select with nothing stored shows its first choice, and the browser posts it.
            $shown = SettingsControls::chosen($field['options'] ?? [], $shown);
        }

        if ($type === 'checkbox') {
            // An unticked switch is not posted: absent is an explicit "off".
            $value = isset($posted[$name]) ? '1' : '0';
            if ($value !== (Truthy::of($shown) ? '1' : '0')) {
                $this->store->save($key, $value);
            }

            return null;
        }

        $raw = $posted[$name] ?? null;
        if (! is_string($raw) || $raw === $shown) {
            return null;
        }

        $value = $this->sanitizer->sanitize($raw, $type, $field['options'] ?? []);
        if ($value === null) {
            return ['name' => $name, 'label' => (string) ($field['label'] ?? $key), 'type' => $type, 'typed' => $raw];
        }

        if (! $this->sanitizer->shouldPreserve($value, $type)) {
            $this->store->save($key, $value);
        }

        return null;
    }
}
