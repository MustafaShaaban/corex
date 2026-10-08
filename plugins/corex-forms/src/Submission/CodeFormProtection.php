<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\FlowProtection;
use Corex\Forms\Form;

/**
 * What a form defined in code declared about its protection, in the shape a flow's has.
 *
 * A flow is protected unless it says otherwise. A form defined in code is protected only when
 * it says so (spec 104): one drawn before this cannot carry a token, and would start refusing
 * every submission on the day its site configured a provider.
 */
final class CodeFormProtection
{
    /**
     * @return array<string,mixed> `captcha` is `on` or `off`; `action` and `threshold` when the form states them.
     */
    public static function of(Form $form): array
    {
        $declared = FlowProtection::normalize($form->protection());

        return ($declared['captcha'] ?? '') === 'on' ? $declared : ['captcha' => 'off'];
    }
}
