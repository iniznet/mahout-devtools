<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * A public signature states a concrete type. mixed where a union is
 * expressible makes every downstream check stop working.
 *
 * @implements ArchitectureRule<Node>
 */
final class NoMixedInPublicSignatureRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noMixedInPublicSignature';

    public function getNodeType(): string
    {
        return FunctionLike::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ClassMethod && !$node instanceof Function_) {
            return [];
        }

        if ($node instanceof ClassMethod && !$node->isPublic()) {
            return [];
        }

        $errors = [];
        $returnType = $node->getReturnType();
        if ($returnType instanceof Identifier && 'mixed' === $returnType->name) {
            $errors[] = Violation::at($node, self::IDENTIFIER, 'A public signature must not return mixed; state a union.');
        }

        foreach ($node->getParams() as $parameter) {
            $type = $parameter->type;
            if ($type instanceof Identifier && 'mixed' === $type->name) {
                $errors[] = Violation::at($node, self::IDENTIFIER, 'A public signature must not accept mixed; state a union.');
            }
        }

        $doc = $node->getDocComment();
        if ($doc !== null) {
            if (1 === preg_match('/@return\s+mixed\b/', $doc->getText())) {
                $errors[] = Violation::at($node, self::IDENTIFIER, 'A public @return must not be mixed; state a union.');
            }
            if (1 === preg_match('/@param\s+mixed\s+\$/', $doc->getText())) {
                $errors[] = Violation::at($node, self::IDENTIFIER, 'A public @param must not be mixed; state a union.');
            }
        }

        return $errors;
    }
}
