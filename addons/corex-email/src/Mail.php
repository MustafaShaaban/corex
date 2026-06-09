<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email;

defined('ABSPATH') || exit;

use Corex\Email\Message\MessageBuilder;
use Corex\Email\Recipients\RecipientResolver;
use Corex\Email\Template\TemplateRegistry;
use Corex\Email\Template\TemplateRenderer;
use Corex\Support\Facades\Corex;

/**
 * The one-line developer API. A bounded framework-boundary accessor (like the Corex
 * facade): it resolves the builder's collaborators from the container so callers
 * write `Mail::to($addr)->template('welcome')->with([...])->send()`.
 */
final class Mail
{
    /**
     * @param string|list<string> $to
     */
    public static function to(string|array $to): MessageBuilder
    {
        return new MessageBuilder(
            $to,
            Corex::make(TemplateRegistry::class),
            Corex::make(TemplateRenderer::class),
            Corex::make(RecipientResolver::class),
            Corex::make(MailService::class),
        );
    }
}
