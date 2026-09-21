<?php
declare(strict_types=1);

namespace Iniznet\Mahout\Fields\Fixture;

// EXPECT-NONE: the field package owns the raw meta call.
function isbn(int $id): string
{
    return (string) get_post_meta($id, 'isbn', true);
}
