<?php
declare(strict_types=1);

namespace Iniznet\Kumki\Support;

// EXPECT-NONE: the boundary is named by position, so a second host's request
// adapter is the same kind of thing as the first one's.
final class Request
{
    public function capacity(): ?string
    {
        return $_GET['capacity'] ?? null;
    }
}
