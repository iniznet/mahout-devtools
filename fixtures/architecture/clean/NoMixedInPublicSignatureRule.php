<?php
declare(strict_types=1);

namespace Fixture\Clean\MixedSignature;

// EXPECT-NONE: the return type is a union, not mixed.
function value(): int|string
{
    return 1;
}
