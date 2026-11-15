<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use Closure;

/**
 * Every variable an Email Studio template can use, grouped by the emails that supply it
 * (spec 120, story 5).
 *
 * A variable is **known** when one of these groups, or the site's own `mail.variables`
 * configuration, names it, or when it is a submission's field (`submission.<field>`): a form's
 * fields are the form's, and a template can use any of them. A known variable that the email a
 * template is bound to does not supply is warned about by name; an unknown one is refused.
 */
final class EmailVariableCatalog
{
    /** Supplied to every email Email Studio draws. */
    public const EVERY = 'every';

    /** A form's emails: a flow's bindings, a code-defined form's route. */
    public const FORM = 'form';

    public const NEWSLETTER = 'newsletter';

    public const ACCOUNT = 'account';

    public const ACCESS = 'access';

    /** Named by the configuration and supplied by no CoreX email: a site's own code fills it. */
    public const OTHER = 'other';

    /** The block that lists every answer of a submission, drawn by CoreX. */
    public const ANSWERS = 'form.answers';

    private const SUBMISSION_FIELD = '/^submission\.[a-z0-9][a-z0-9_-]*\z/';

    /** @var array<string,list<string>> */
    private const SUPPLIED = [
        self::EVERY      => ['site.name', 'site.url', 'links.privacy', 'recipient.email'],
        self::FORM       => [
            'submission.name', 'submission.email', 'submission.message', 'submission.body',
            'form.title', self::ANSWERS, 'links.submission', 'failure.message',
        ],
        self::NEWSLETTER => ['action.url', 'links.unsubscribe', 'links.preferences'],
        self::ACCOUNT    => ['user.name', 'user.email', 'action.url', 'account.url', 'reset.expires', 'reset.asked'],
        self::ACCESS     => [
            'request.id', 'request.ability_key', 'request.area_key', 'request.decision', 'request.note',
            'requester.name', 'requester.email', 'reviewer.name',
        ],
    ];

    /** @var array<string,array{type?:string,label?:string}>|null */
    private ?array $resolved = null;

    /**
     * @param array<string,array{type?:string,label?:string,emails?:list<string>}>|Closure(): array<string,array{type?:string,label?:string,emails?:list<string>}> $configured
     *        The `mail.variables` configuration, or what works it out. Worked out on first use: other
     *        plugins declare theirs on `corex_email_variable_schema` as WordPress starts (spec 122,
     *        FR-023), after this catalog is built.
     */
    public function __construct(private readonly array|Closure $configured = [])
    {
    }

    /** @return array<string,array{type?:string,label?:string}> */
    private function configured(): array
    {
        if ($this->resolved === null) {
            $configured = $this->configured instanceof Closure ? ($this->configured)() : $this->configured;
            $this->resolved = is_array($configured) ? $configured : [];
        }

        return $this->resolved;
    }

    public function isKnown(string $key): bool
    {
        return $this->groupOf($key) !== null || preg_match(self::SUBMISSION_FIELD, $key) === 1;
    }

    /**
     * Whether an email of this group supplies the variable: every email supplies the site's,
     * and a form's email supplies any of its fields.
     */
    public function supplies(string $group, string $key): bool
    {
        if (in_array($key, self::SUPPLIED[self::EVERY], true)) {
            return true;
        }
        if ($group === self::FORM && preg_match(self::SUBMISSION_FIELD, $key) === 1) {
            return true;
        }

        return in_array($key, $this->supplied()[$group] ?? [], true);
    }

    /**
     * What each group of emails supplies: CoreX's own, and each variable another plugin declares
     * for the emails it names (`emails`; the forms engine for flow emails, spec 122).
     *
     * @return array<string,list<string>>
     */
    private function supplied(): array
    {
        $supplied = self::SUPPLIED;
        foreach ($this->configured() as $key => $definition) {
            foreach ((array) ($definition['emails'] ?? []) as $group) {
                if (isset($supplied[$group]) && ! in_array($key, $supplied[$group], true)) {
                    $supplied[$group][] = (string) $key;
                }
            }
        }

        return $supplied;
    }

    /** The emails of a group, as a sentence names them: "a form email". */
    public function emailLabel(string $group): string
    {
        return match ($group) {
            self::FORM       => __('a form email', 'corex'),
            self::NEWSLETTER => __('a newsletter email', 'corex'),
            self::ACCOUNT    => __('an account email', 'corex'),
            self::ACCESS     => __('an access request email', 'corex'),
            default          => __('this email', 'corex'),
        };
    }

    /**
     * The variables, by the emails that supply them, each with its label: what the Variables tab
     * lists.
     *
     * @return list<array{key:string,label:string,variables:list<array{key:string,label:string}>}>
     */
    public function groups(): array
    {
        $groups = [];
        $named = [];
        foreach ($this->supplied() as $group => $keys) {
            $groups[] = $this->group($group, $keys);
            $named = [...$named, ...$keys];
        }
        $other = array_values(array_diff(array_keys($this->configured()), $named));
        if ($other !== []) {
            $groups[] = $this->group(self::OTHER, $other);
        }

        return $groups;
    }

    /** A variable's label, as the Variables tab and the preview's samples name it. */
    public function label(string $key): string
    {
        $label = match ($key) {
            'site.name'           => __('Site name', 'corex'),
            'site.url'            => __('Site address', 'corex'),
            'links.privacy'       => __('Privacy page', 'corex'),
            'recipient.email'     => __('Recipient email', 'corex'),
            'recipient.name'      => __('Recipient name', 'corex'),
            'submission.name'     => __('Name the visitor gave', 'corex'),
            'submission.email'    => __('Email the visitor gave', 'corex'),
            'submission.message'  => __('Message the visitor wrote', 'corex'),
            'submission.body'     => __('Submission body', 'corex'),
            'form.title'          => __('Form title', 'corex'),
            self::ANSWERS         => __('All answers', 'corex'),
            'links.submission'    => __('Link to the submission', 'corex'),
            'failure.message'     => __('Why the email failed', 'corex'),
            'action.url'          => __('Action link (confirm, reset)', 'corex'),
            'links.unsubscribe'   => __('Unsubscribe link', 'corex'),
            'links.preferences'   => __('Preferences link', 'corex'),
            'user.name'           => __('Account name', 'corex'),
            'user.email'          => __('Account email', 'corex'),
            'account.url'         => __('Account page', 'corex'),
            'reset.expires'       => __('How long the reset link works', 'corex'),
            'reset.asked'         => __('When the reset was asked', 'corex'),
            'request.id'          => __('Access request number', 'corex'),
            'request.ability_key' => __('Ability asked for', 'corex'),
            'request.area_key'    => __('Area asked for', 'corex'),
            'request.decision'    => __('Decision', 'corex'),
            'request.note'        => __('Reviewer note', 'corex'),
            'requester.name'      => __('Requester name', 'corex'),
            'requester.email'     => __('Requester email', 'corex'),
            'reviewer.name'       => __('Reviewer name', 'corex'),
            default               => '',
        };
        if ($label !== '') {
            return $label;
        }
        if (preg_match(self::SUBMISSION_FIELD, $key) === 1) {
            /* translators: %s: a form field's key. */
            return sprintf(__('Form field "%s"', 'corex'), substr($key, strlen('submission.')));
        }

        return (string) ($this->configured()[$key]['label'] ?? $key);
    }

    private function groupOf(string $key): ?string
    {
        foreach ($this->supplied() as $group => $keys) {
            if (in_array($key, $keys, true)) {
                return $group;
            }
        }

        return isset($this->configured()[$key]) ? self::OTHER : null;
    }

    /**
     * @param list<string> $keys
     *
     * @return array{key:string,label:string,variables:list<array{key:string,label:string}>}
     */
    private function group(string $group, array $keys): array
    {
        return [
            'key'       => $group,
            'label'     => match ($group) {
                self::EVERY      => __('Every email', 'corex'),
                self::FORM       => __('Form emails', 'corex'),
                self::NEWSLETTER => __('Newsletter emails', 'corex'),
                self::ACCOUNT    => __('Account emails', 'corex'),
                self::ACCESS     => __('Access request emails', 'corex'),
                default          => __('Not supplied by CoreX: your site\'s code fills these', 'corex'),
            },
            'variables' => array_map(
                fn (string $key): array => ['key' => $key, 'label' => $this->label($key)],
                $keys,
            ),
        ];
    }
}
