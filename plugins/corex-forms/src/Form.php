<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms;

defined('ABSPATH') || exit;

use Corex\Forms\Block\FormParts;
use Corex\Forms\Listeners\SendEmailListener;
use Corex\Forms\Listeners\StoreSubmissionListener;

/**
 * A code-defined form: its slug, its field definitions, its wording, and the listeners
 * that handle its submissions. The single source feeding the schema resolver, the
 * submit endpoint, and the block. A concrete form sets $slug and $fields.
 */
abstract class Form
{
    public string $slug = '';

    /**
     * @var array<string,array{type?:string,rules?:list<string>,label?:string}>
     */
    protected array $fields = [];

    /**
     * @return array<string,array{type?:string,rules?:list<string>,label?:string}>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * A human label for the form, shown in the block's form selector. Defaults to a
     * humanized slug; a concrete form may override for a nicer name.
     */
    public function label(): string
    {
        return $this->slug === '' ? '' : ucwords(str_replace(['-', '_'], ' ', $this->slug));
    }

    /**
     * What the submit button reads. Empty, the default, is CoreX's own label; so is a label
     * of nothing but spaces, because a button needs a name.
     */
    public function submitLabel(): string
    {
        return '';
    }

    /**
     * What a visitor is told when a submission is accepted. Empty, the default, is CoreX's
     * own confirmation.
     */
    public function successMessage(): string
    {
        return '';
    }

    /**
     * What a visitor is told, beside each field's own message, when a submission is refused
     * for its answers. Empty, the default, is CoreX's own wording.
     */
    public function errorMessage(): string
    {
        return '';
    }

    /**
     * Whether a submission must pass the site's challenge provider. Off by default: return
     * `['captcha' => 'on']` to ask for it. `action` and `threshold` may be added for a provider
     * that scores a visitor; without them the action is derived from the slug and the threshold
     * is the site's. On a site with no provider configured a protected form is accepted under
     * the trap field, the security token and the rate limit, as every form is.
     *
     * @return array{captcha?:string,action?:string,threshold?:float}
     */
    public function protection(): array
    {
        return ['captcha' => 'off'];
    }

    /**
     * The form's own markup, built from the parts it is handed; `null`, the default, is the
     * form CoreX draws.
     *
     * The markup must hold `$parts->attributes()` on its `<form>`, and `$parts->hidden()` and
     * `$parts->status()` inside it. Each field needs a wrapper carrying
     * `$parts->fieldAttributes($name)` that holds its control and `$parts->error($name)`.
     */
    public function markup(FormParts $parts): ?string
    {
        return null;
    }

    /**
     * The values a valid submission is stored and sent with (spec 129, FR-022): run once after
     * validation and before any listener, so the inbox and the team's email read the same thing.
     * The default changes nothing. A form may turn a raw answer into what a person reads, such as
     * a time sent as a timestamp; it must not add a field its schema does not declare.
     *
     * @param array<string,mixed> $values validated answers, uploads already stored
     *
     * @return array<string,mixed>
     */
    public function prepare(array $values): array
    {
        return $values;
    }

    /**
     * Listener service ids for this form's submissions. The default set stores the
     * submission and emails a notification; concrete forms may override.
     *
     * @return list<class-string>
     */
    public function listeners(): array
    {
        return [StoreSubmissionListener::class, SendEmailListener::class];
    }
}
