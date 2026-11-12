<?php

/**
 * Plugin Name: CoreX E2E file form (test fixture)
 * Description: Registers a form that asks for a file, so the browser suite can upload one and open it from the Submissions inbox.
 * Version: 1.0.0
 *
 * @package Corex\Tests\E2E
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * A form defined in code with a file field (`submission-file.spec.js`).
 *
 * A file reaches a submission only through a form defined in code: a flow built in the admin
 * takes none. No kit ships such a form, so an install has none to upload through, and the
 * integration suite cannot make an upload WordPress accepts (it is not `is_uploaded_file()`).
 * With this, the browser sends a real one.
 *
 * It stores the submission and sends no notification: a development install may deliver for
 * real, and a test must never hand a message to a real mailbox.
 *
 * An mu-plugin so it is active with no activation step. How the fixtures reach an install is in
 * `corex-e2e-core-notices.php`.
 */
add_action('init', static function (): void {
    if (! class_exists(\Corex\Forms\Form::class) || ! class_exists(\Corex\Boot::class)) {
        return;
    }

    \Corex\Boot::app()->container()->make(\Corex\Forms\FormRegistry::class)->register(new class extends \Corex\Forms\Form {
        public string $slug = 'corex-e2e-file-form';

        protected array $fields = [
            'name' => ['type' => 'text', 'label' => 'Name', 'rules' => ['required']],
            'email' => ['type' => 'email', 'label' => 'Email', 'rules' => ['required', 'email']],
            'cv' => ['type' => 'file', 'label' => 'Your CV', 'rules' => ['mime:application/pdf']],
        ];

        public function listeners(): array
        {
            return [\Corex\Forms\Listeners\StoreSubmissionListener::class];
        }
    });
});
