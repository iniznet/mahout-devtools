<?php
declare(strict_types=1);

namespace Fixture\Violations\StaticService;

final class SeriesRepository
{
    public static function find(): void
    {
    }
}

function load(): void
{
    SeriesRepository::find(); // EXPECT: mahout.arch.noStaticServiceAccess
}
