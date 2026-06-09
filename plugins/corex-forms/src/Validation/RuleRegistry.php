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
use Corex\Forms\Validation\Rules\Required;
use InvalidArgumentException;

/**
 * Maps a rule name to its Rule instance and parses a rule spec ("max:80") into a
 * name and its parameters. The v1 rule set is fixed (`required`/`email`/`max`/
 * `min`/`numeric`); unknown names are reported, never silently accepted.
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
            'numeric'  => new Numeric(),
        ];
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
