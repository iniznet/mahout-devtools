<?php
declare(strict_types=1);

namespace Fixture\Violations\MixedSignature;

function value(): mixed // EXPECT: mahout.arch.noMixedInPublicSignature
{
    return 1;
}
