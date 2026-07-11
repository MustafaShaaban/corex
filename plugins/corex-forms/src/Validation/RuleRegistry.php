<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Validation;

defined('ABSPATH') || exit;

use Corex\Forms\Validation\Rules\Email;
use Corex\Forms\Validation\Rules\Max;
use Corex\Forms\Validation\Rules\Min;
use Corex\Forms\Validation\Rules\Numeric;
use Corex\Forms\Validation\Rules\Pattern;
use Corex\Forms\Validation\Rules\Required;
use Corex\Forms\Validation\Rules\Url;
use InvalidArgumentException;

/**
 * Maps a rule name to its Rule instance and parses a rule spec ("max:80") into a
 * name and its parameters. Built-ins cover the complete flow contract while
 * extensions may register additional rules; unknown names are never accepted.
 */
final class RuleRegistry
{
    /**
     * @var array<string,Rule>
     */
    private array $rules;

    public function __construct()
    {
        $this->rules = [
            'required' => new Required(),
            'email'    => new Email(),
            'max'      => new Max(),
            'min'      => new Min(),
            'max_length' => new Max(),
            'min_length' => new Min(),
            'numeric'  => new Numeric(),
            'url'      => new Url(),
            'pattern'  => new Pattern(),
        ];
    }

    public function register(string $name, Rule $rule): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $name) || isset($this->rules[$name])) {
            throw new InvalidArgumentException(sprintf('Invalid or duplicate validation rule: "%s".', $name));
        }

        $this->rules[$name] = $rule;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->rules);
    }

    public function has(string $name): bool
    {
        return isset($this->rules[$name]);
    }

    public function get(string $name): Rule
    {
        if (! isset($this->rules[$name])) {
            throw new InvalidArgumentException(sprintf('Unknown validation rule: "%s".', $name));
        }

        return $this->rules[$name];
    }

    /**
     * Split "name:param1,param2" into its name and parameter list.
     *
     * @return array{name:string,params:list<string>}
     */
    public function parse(string $spec): array
    {
        [$name, $rest] = array_pad(explode(':', $spec, 2), 2, null);

        $params = ($rest === null || $rest === '') ? [] : explode(',', $rest);

        return ['name' => (string) $name, 'params' => $params];
    }
}
