<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A nonce in a Shared Surface is a cached session artefact: one visitor's token
 * replayed to another. The collector joins the declaration to the call site.
 *
 * @implements ArchitectureRule<CollectedDataNode>
 */
final class NoNonceInSharedSurfaceRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noNonceInSharedSurface';

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        $shared = [];
        $nonces = [];
        foreach ($node->get(SharedSurfaceCollector::class) as $file => $items) {
            foreach ($items as $item) {
                if (!is_array($item) || !isset($item['kind'], $item['class']) || !is_string($item['kind']) || !is_string($item['class'])) {
                    continue;
                }

                $class = $item['class'];
                if (SharedSurfaceCollector::SHARED === $item['kind'] && '' !== $class) {
                    $shared[$class] = true;
                }

                if (SharedSurfaceCollector::NONCE === $item['kind'] && '' !== $class) {
                    $line = isset($item['line']) && is_int($item['line']) ? $item['line'] : 1;
                    $nonces[] = ['file' => $file, 'class' => $class, 'line' => $line];
                }
            }
        }

        $errors = [];
        foreach ($nonces as $nonce) {
            if (!isset($shared[$nonce['class']])) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Class %s declares Cacheability::Shared and creates a nonce; a nonce is forbidden in a Shared Surface.',
                $nonce['class'],
            ))->identifier(self::IDENTIFIER)->file($nonce['file'])->line($nonce['line'])->build();
        }

        return $errors;
    }
}
