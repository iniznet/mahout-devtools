<?php
declare(strict_types=1);

namespace Fixture\Violations\Extract;

function unpack(array $data): void
{
    extract($data, EXTR_SKIP); // EXPECT: mahout.arch.noExtract
}
