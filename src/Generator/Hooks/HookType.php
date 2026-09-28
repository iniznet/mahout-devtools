<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Generator\Hooks;

use Iniznet\Mahout\Devtools\Exception\InvalidHookDeclaration;

/**
 * The two kinds of hook, and the artefact each one owns.
 *
 * A hook is an action or a filter, and that is the one distinction a reader of the
 * reference actually branches on: an integrator attaching to a hook needs the list of
 * hooks that fire and forget, or the list of hooks that return a value. The reference is
 * therefore two documents, and this enum is the only place that names them — a file name
 * spelled in a composer script as well as here would be a second source of truth.
 *
 * @internal
 */
enum HookType: string
{
    case Action = 'action';
    case Filter = 'filter';

    /**
     * The kind as the docblock tag spells it, refusing anything else loudly.
     */
    public static function fromTag(string $tag, string $class, string $constant, string $file): self
    {
        $type = self::tryFrom($tag);

        if (null === $type) {
            throw InvalidHookDeclaration::missingType($class, $constant, $file);
        }

        return $type;
    }

    /**
     * The file this kind's reference is written to, relative to the output directory.
     */
    public function fileName(): string
    {
        return match ($this) {
            self::Action => 'actions.md',
            self::Filter => 'filters.md',
        };
    }

    /**
     * The heading the document carries.
     */
    public function heading(): string
    {
        return match ($this) {
            self::Action => 'Action hooks',
            self::Filter => 'Filter hooks',
        };
    }

    /**
     * A short noun for the artefact, used in the gate message.
     */
    public function kind(): string
    {
        return match ($this) {
            self::Action => 'action hook reference',
            self::Filter => 'filter hook reference',
        };
    }

    /**
     * The document that holds the other kind, so a reader is pointed at it rather than
     * concluding the absence is an omission.
     */
    public function siblingFileName(): string
    {
        return match ($this) {
            self::Action => self::Filter->fileName(),
            self::Filter => self::Action->fileName(),
        };
    }
}
