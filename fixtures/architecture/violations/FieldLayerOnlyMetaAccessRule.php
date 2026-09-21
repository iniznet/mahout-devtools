<?php
declare(strict_types=1);

namespace Fixture\Violations\FieldAccess;

function isbn(int $id): string
{
    return (string) get_post_meta($id, 'isbn', true); // EXPECT: mahout.arch.fieldLayerOnlyMetaAccess
}
