<?php
declare(strict_types=1);

namespace Fixture\Violations\MetaQuery;

function query(array $meta_query): void // EXPECT: mahout.arch.noMetaQueryInPublicApi
{
}
