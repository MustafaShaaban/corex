<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

/**
 * What the preview and the test send fill each variable with (spec 120, story 1): believable
 * values, the site's real name and address, and never a real submission's.
 */
final readonly class EmailSampleValues
{
    public function __construct(private EmailVariableCatalog $catalog)
    {
    }

    /**
     * The context a template is drawn with, for the variables it uses, and the list shown beside
     * the preview.
     *
     * @param list<string>          $keys      The variables the template uses.
     * @param array<string,mixed>   $overrides Values typed beside the preview, by variable; used
     *                                         for this draw only.
     *
     * @return array{context:array<string,mixed>,list:list<array{key:string,label:string,value:string,editable:bool}>}
     */
    public function for(array $keys, array $overrides = []): array
    {
        $context = ['form' => ['answers' => $this->answers()]];
        $list = [];
        foreach (array_values(array_unique($keys)) as $key) {
            if ($key === EmailVariableCatalog::ANSWERS) {
                $list[] = [
                    'key' => $key,
                    'label' => $this->catalog->label($key),
                    'value' => __('Three sample answers', 'corex'),
                    'editable' => false,
                ];
                continue;
            }
            $given = $overrides[$key] ?? null;
            $value = is_scalar($given) ? (string) $given : $this->valueOf($key);
            $context = $this->placed($context, $key, $value);
            $list[] = ['key' => $key, 'label' => $this->catalog->label($key), 'value' => $value, 'editable' => true];
        }

        return ['context' => $context, 'list' => $list];
    }

    /** One variable's sample. */
    public function valueOf(string $key): string
    {
        $home = (string) home_url('/');

        return match ($key) {
            'site.name'                                  => wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES),
            'site.url', 'account.url'                    => $home,
            'links.privacy'                              => (string) get_privacy_policy_url() ?: $home,
            'recipient.email', 'submission.email',
            'user.email', 'requester.email'              => 'jane@example.com',
            'recipient.name', 'submission.name',
            'user.name', 'requester.name'                => 'Jane Cooper',
            'submission.message', 'submission.body'      => __('Could we book a call next week to talk about our new site?', 'corex'),
            'form.title'                                 => __('Contact form', 'corex'),
            'links.submission'                           => (string) admin_url('admin.php?page=corex-submissions'),
            'failure.message'                            => __('The mail server did not accept the email.', 'corex'),
            'action.url'                                 => add_query_arg('corex_sample', '1', $home),
            'links.unsubscribe', 'links.preferences'     => add_query_arg('corex_sample', '1', $home),
            'reset.expires'                              => __('24 hours', 'corex'),
            'reset.asked'                                => wp_date((string) get_option('date_format') . ' ' . (string) get_option('time_format')),
            'request.id'                                 => '42',
            'request.ability_key'                        => __('Manage forms', 'corex'),
            'request.area_key'                           => __('Forms', 'corex'),
            'request.decision'                           => __('approved', 'corex'),
            'request.note'                               => __('Granted for the launch week.', 'corex'),
            'reviewer.name'                              => 'Sam Lee',
            /* translators: %s: a variable's last part, such as "phone". */
            default                                      => sprintf(__('Sample %s', 'corex'), (string) substr((string) strrchr('.' . $key, '.'), 1)),
        };
    }

    /** @return list<array{label:string,value:string}> */
    private function answers(): array
    {
        return [
            ['label' => __('Name', 'corex'), 'value' => 'Jane Cooper'],
            ['label' => __('Email', 'corex'), 'value' => 'jane@example.com'],
            ['label' => __('Message', 'corex'), 'value' => __('Could we book a call next week to talk about our new site?', 'corex')],
        ];
    }

    /**
     * @param array<string,mixed> $context
     *
     * @return array<string,mixed>
     */
    private function placed(array $context, string $key, string $value): array
    {
        $segments = explode('.', $key);
        $node = &$context;
        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $node[$segment] = $value;
                break;
            }
            if (! isset($node[$segment]) || ! is_array($node[$segment])) {
                $node[$segment] = [];
            }
            $node = &$node[$segment];
        }
        unset($node);

        return $context;
    }
}
