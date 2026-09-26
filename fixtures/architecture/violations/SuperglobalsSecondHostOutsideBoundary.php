<?php
declare(strict_types=1);

namespace Iniznet\Kumki\Admin;

// EXPECT: mahout.arch.superglobalsOnlyInRequest
// Generalising the boundary to a position does not generalise it to everything:
// a second host's admin class is outside its own request adapter exactly as a
// first host's would be.
final class Columns
{
    public function sort(): string
    {
        return $_GET['orderby'] ?? '';
    }
}
