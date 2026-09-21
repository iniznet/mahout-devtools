<?php
declare(strict_types=1);

namespace Iniznet\Howdah\Support;

// EXPECT-NONE: the request adapter is the one boundary.
final class Request
{
    public function genre(): string
    {
        return $_GET['genre'] ?? '';
    }
}
