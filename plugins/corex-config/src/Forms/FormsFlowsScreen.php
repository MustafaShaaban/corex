<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Forms;

use Corex\Admin\AdminPage;
use Corex\Container\ContainerInterface;
use Corex\Forms\FormRegistry;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * The Forms & Flows admin screen (spec 063, Phase 2): a truthful, read-only inventory of the REAL
 * code-defined CoreX forms and their fields. It reads the live {@see FormRegistry} (resolved lazily so
 * corex-config never hard-depends on corex-forms — Principle IX; an absent registry renders an honest
 * "unavailable" state), shapes it through the pure {@see FormsOverview}, and prints escaped, translated
 * markup. Forms are registered in code today; the visual builder is a future capability, labelled as
 * such — never shown as a working feature. No fabricated forms, fields, or submission data.
 */
final class FormsFlowsScreen
{
    private string $hook = '';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly AdminPage $page,
        private readonly FormsOverview $overview,
        private readonly ContainerInterface $container,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'maybeEnqueue']);
    }

    public function menu(): void
    {
        $this->hook = (string) add_submenu_page(
            'corex-settings',
            __('CoreX Forms & Flows', 'corex'),
            __('Forms & Flows', 'corex'),
            'manage_options',
            'corex-forms',
            [$this, 'render'],
            25,
        );
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }

        wp_enqueue_style(
            'corex-forms-admin',
            plugins_url('assets/forms-admin.css', COREX_CONFIG_FILE),
            ['corex-admin-shell'],
            '1.0.0',
        );
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            echo $this->page->permissionDenied('forms');

            return;
        }

        echo $this->page->open(
            'forms',
            __('CoreX Forms & Flows', 'corex'),
            __('The forms registered in this site, their fields, and where their submissions go.', 'corex'),
        );

        $summary = $this->overview->summary($this->registeredForms());

        echo $this->developerNote();

        if ($summary['isEmpty']) {
            echo $this->page->state(
                'empty',
                __('No forms registered', 'corex'),
                __('CoreX forms are registered in code. The default Contact form appears here once corex-forms is active.', 'corex'),
            );
            echo $this->page->close();

            return;
        }

        echo $this->summaryBar($summary);
        echo '<div class="corex-forms-admin__list">';
        foreach ($summary['forms'] as $form) {
            echo $this->formCard($form);
        }
        echo '</div>' . $this->page->close();
    }

    /**
     * The truthful development note: forms are code-first today; the visual builder is a future
     * capability shown as reference, never as a working feature.
     */
    private function developerNote(): string
    {
        return '<div class="corex-forms-admin__note corex-surface">'
            . '<p class="corex-forms-admin__note-title">' . esc_html__('Code-first forms', 'corex') . '</p>'
            . '<p class="corex-forms-admin__note-text">'
            . esc_html__(
                'CoreX forms are defined in code (slug, fields, validation, and submission routing). A visual form/flow builder is a planned future capability — this screen is a read-only inventory of what is registered now.',
                'corex',
            )
            . '</p></div>';
    }

    /**
     * @param array{count:int,fieldTotal:int} $summary
     */
    private function summaryBar(array $summary): string
    {
        return '<div class="corex-forms-admin__summary">'
            . $this->summaryStat(__('Forms', 'corex'), (string) $summary['count'])
            . $this->summaryStat(__('Fields', 'corex'), (string) $summary['fieldTotal'])
            . $this->summaryStat(__('Submissions', 'corex'), '<a href="'
                . esc_url(admin_url('admin.php?page=corex-submissions')) . '">' . esc_html__('Open Submissions Inbox', 'corex') . '</a>')
            . '</div>';
    }

    private function summaryStat(string $label, string $valueHtml): string
    {
        return '<div class="corex-forms-admin__summary-card"><p class="corex-forms-admin__summary-label">'
            . esc_html($label) . '</p><p class="corex-forms-admin__summary-value">' . wp_kses_post($valueHtml)
            . '</p></div>';
    }

    /**
     * @param array{slug:string,label:string,fieldCount:int,fields:list<array{name:string,type:string,label:string,required:bool,rules:list<string>}>} $form
     */
    private function formCard(array $form): string
    {
        $rows = '';
        foreach ($form['fields'] as $field) {
            $required = $field['required']
                ? '<span class="corex-forms-admin__req">' . esc_html__('required', 'corex') . '</span>'
                : '<span class="corex-forms-admin__opt">' . esc_html__('optional', 'corex') . '</span>';

            $rows .= '<tr><td><code>' . esc_html($field['name']) . '</code></td>'
                . '<td>' . esc_html($field['label']) . '</td>'
                . '<td><span class="corex-forms-admin__type">' . esc_html($field['type']) . '</span></td>'
                . '<td>' . $required . '</td>'
                . '<td>' . esc_html(implode(', ', $field['rules'])) . '</td></tr>';
        }

        return sprintf(
            '<section class="corex-surface corex-forms-admin__card" id="corex-form-%1$s">'
            . '<header class="corex-forms-admin__card-head"><h2>%2$s</h2>'
            . '<code class="corex-forms-admin__slug">%3$s</code>'
            . '<span class="corex-forms-admin__count">%4$s</span></header>'
            . '<table class="corex-forms-admin__fields"><thead><tr>'
            . '<th>%5$s</th><th>%6$s</th><th>%7$s</th><th>%8$s</th><th>%9$s</th>'
            . '</tr></thead><tbody>%10$s</tbody></table></section>',
            esc_attr($form['slug']),
            esc_html($form['label']),
            esc_html($form['slug']),
            sprintf(
                /* translators: %d: number of fields in the form */
                esc_html(_n('%d field', '%d fields', $form['fieldCount'], 'corex')),
                (int) $form['fieldCount'],
            ),
            esc_html__('Field', 'corex'),
            esc_html__('Label', 'corex'),
            esc_html__('Type', 'corex'),
            esc_html__('Requirement', 'corex'),
            esc_html__('Validation', 'corex'),
            $rows,
        );
    }

    /**
     * The real registered forms, resolved lazily. Reads each form's own field definition (the truthful
     * source), mapping validation rules and the required flag. An absent registry (corex-forms inactive)
     * yields an empty list, which the caller renders as an honest empty state — never an error.
     *
     * @return list<array{slug:string,label:string,fields:list<array{name:string,type:string,label:string,required:bool,rules:list<string>}>}>
     */
    private function registeredForms(): array
    {
        try {
            /** @var FormRegistry $registry */
            $registry = $this->container->make(FormRegistry::class);
        } catch (\Throwable) {
            return [];
        }

        $forms = [];
        foreach ($registry->all() as $form) {
            $fields = [];
            foreach ($form->fields() as $name => $definition) {
                $rules      = array_map('strval', (array) ($definition['rules'] ?? []));
                $fields[]   = [
                    'name'     => (string) $name,
                    'type'     => (string) ($definition['type'] ?? 'text'),
                    'label'    => (string) ($definition['label'] ?? $name),
                    'required' => in_array('required', $rules, true),
                    'rules'    => $rules,
                ];
            }

            $forms[] = [
                'slug'   => $form->slug,
                'label'  => $form->label(),
                'fields' => $fields,
            ];
        }

        return $forms;
    }
}
