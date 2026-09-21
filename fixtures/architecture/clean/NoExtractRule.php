<?php
declare(strict_types=1);

namespace Fixture\Clean\Extract;

// EXPECT-NONE: the value is read by name.
function unpack(array $data): string
{
    return (string) ($data['title'] ?? '');
}
