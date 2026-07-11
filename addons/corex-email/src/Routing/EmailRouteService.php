<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Routing;

defined('ABSPATH') || exit;

use Corex\Mail\MailResult;
use Corex\Mail\RoutedMailer;
use Corex\Mail\TemplateMailer;

/**
 * Resolves declarative route rules against caller-provided context only.
 */
final class EmailRouteService implements RoutedMailer, TemplateMailer
{
    public function __construct(
        private readonly EmailRouteRepository $routes,
        private readonly ?EmailRouteDispatcher $dispatcher = null,
    ) {
    }

    /** @param array<string,mixed> $context */
    public function resolve(string $trigger, array $context): ?ResolvedEmailRoute
    {
        $route = $this->routes->findByTrigger($trigger);
        if ($route === null || ! $route->enabled) {
            return null;
        }

        $recipients = [];
        foreach ($route->recipientRules as $rule) {
            $recipient = $this->resolveRule($rule, $context);
            if ($recipient !== null) {
                $recipients[] = $recipient;
            }
        }
        $recipients = array_values(array_unique($recipients));
        if ($recipients === []) {
            return null;
        }

        $replyTo = $route->replyToRule === null ? null : $this->resolveRule($route->replyToRule, $context);

        return new ResolvedEmailRoute($route->id, $route->templateId, $recipients, $replyTo);
    }

    public function dispatch(string $trigger, array $context): ?MailResult
    {
        if ($this->dispatcher === null) {
            return null;
        }

        $route = $this->resolve($trigger, $context);
        if ($route === null) {
            return null;
        }

        return $this->dispatcher->dispatch($route, $context);
    }

    public function dispatchTemplate(
        int $templateId,
        array $recipients,
        ?string $replyTo,
        array $context,
    ): ?MailResult {
        if ($this->dispatcher === null || $templateId < 1 || $recipients === []) {
            return null;
        }

        return $this->dispatcher->dispatch(
            new ResolvedEmailRoute(null, $templateId, $recipients, $replyTo),
            $context,
        );
    }

    /**
     * @param array{source:string,path?:string,value?:string} $rule
     * @param array<string,mixed>                             $context
     */
    private function resolveRule(array $rule, array $context): ?string
    {
        $value = $rule['source'] === 'literal'
            ? ($rule['value'] ?? '')
            : $this->contextValue($context, $rule['path'] ?? '');

        return filter_var($value, FILTER_VALIDATE_EMAIL) === false ? null : $value;
    }

    /** @param array<string,mixed> $context */
    private function contextValue(array $context, string $path): string
    {
        $value = $context;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return '';
            }
            $value = $value[$segment];
        }

        return is_string($value) ? $value : '';
    }
}
