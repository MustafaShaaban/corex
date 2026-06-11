<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Docs;

defined('ABSPATH') || exit;

use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Reads one PHP file into a ClassDoc by parsing it to an AST — never by loading the
 * class — so it is pure, side-effect-free, and headless-testable, and works on code
 * with unmet runtime dependencies. Returns null for a file with no named class-like.
 */
final class ClassDocReader
{
    private readonly Parser $parser;
    private readonly NodeFinder $finder;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForHostVersion();
        $this->finder = new NodeFinder();
    }

    public function read(string $file): ?ClassDoc
    {
        $code = (string) file_get_contents($file);
        $ast = $this->parser->parse($code);

        if ($ast === null) {
            return null;
        }

        $classNode = $this->finder->findFirst($ast, static fn (Node $n): bool => $n instanceof ClassLike && $n->name !== null);

        if (! $classNode instanceof ClassLike || $classNode->name === null) {
            return null;
        }

        $namespaceNode = $this->finder->findFirstInstanceOf($ast, Namespace_::class);
        $namespace = $namespaceNode instanceof Namespace_ && $namespaceNode->name !== null
            ? $namespaceNode->name->toString()
            : '';

        $short = $classNode->name->toString();
        $fqcn = $namespace !== '' ? $namespace . '\\' . $short : $short;

        return new ClassDoc(
            $fqcn,
            $short,
            $namespace,
            $this->kind($classNode),
            $this->summary($classNode->getDocComment()?->getText() ?? ''),
            $this->methods($classNode),
        );
    }

    private function kind(ClassLike $node): string
    {
        return match (true) {
            $node instanceof Interface_ => 'interface',
            $node instanceof Trait_     => 'trait',
            $node instanceof Enum_      => 'enum',
            default                     => 'class',
        };
    }

    /**
     * @return list<array{signature:string,summary:string}>
     */
    private function methods(ClassLike $node): array
    {
        $methods = [];

        foreach ($node->getMethods() as $method) {
            if (! $method->isPublic()) {
                continue;
            }

            $methods[] = [
                'signature' => $this->signature($method),
                'summary'   => $this->summary($method->getDocComment()?->getText() ?? ''),
            ];
        }

        return $methods;
    }

    private function signature(ClassMethod $method): string
    {
        $params = array_map(fn (Param $p): string => $this->param($p), $method->params);
        $return = $method->returnType !== null ? ': ' . $this->typeToString($method->returnType) : '';

        return $method->name->toString() . '(' . implode(', ', $params) . ')' . $return;
    }

    private function param(Param $param): string
    {
        $type = $param->type !== null ? $this->typeToString($param->type) . ' ' : '';
        $variadic = $param->variadic ? '...' : '';
        $name = $param->var instanceof Node\Expr\Variable && is_string($param->var->name)
            ? '$' . $param->var->name
            : '$_';

        return $type . $variadic . $name;
    }

    private function typeToString(Node $node): string
    {
        if ($node instanceof NullableType) {
            return '?' . $this->typeToString($node->type);
        }

        if ($node instanceof UnionType) {
            return implode('|', array_map(fn (Node $t): string => $this->typeToString($t), $node->types));
        }

        if ($node instanceof IntersectionType) {
            return implode('&', array_map(fn (Node $t): string => $this->typeToString($t), $node->types));
        }

        if ($node instanceof Identifier || $node instanceof Name) {
            return $node->toString();
        }

        return $node instanceof ComplexType ? 'mixed' : '';
    }

    /**
     * The leading description of a docblock: the prose before the first `@tag`,
     * with the comment markers stripped and whitespace collapsed to one paragraph.
     */
    private function summary(string $doc): string
    {
        if ($doc === '') {
            return '';
        }

        $out = [];

        foreach (preg_split('/\R/', $doc) ?: [] as $line) {
            // Strip the trailing `*/` (single-line docblocks) then the leading
            // `/**` or ` * ` markers, leaving just the prose.
            $clean = (string) preg_replace('#\*+/\s*$#', '', $line);
            $clean = trim((string) preg_replace('#^\s*/?\*+/?#', '', $clean));

            if (str_starts_with($clean, '@')) {
                break;
            }

            if ($clean === '' && $out === []) {
                continue;
            }

            $out[] = $clean;
        }

        return trim((string) preg_replace('/\s+/', ' ', implode(' ', $out)));
    }
}
