<?php
declare(strict_types=1);

namespace Fixture\Clean\StaticService;

final class Slug
{
    public static function fromString(string $value): self
    {
        return new self();
    }
}

// EXPECT-NONE: a value constructor holds no collaborator.
function build(): Slug
{
    return Slug::fromString('a');
}
