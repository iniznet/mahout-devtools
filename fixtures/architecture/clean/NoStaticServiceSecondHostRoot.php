<?php
declare(strict_types=1);

namespace Iniznet\Kumki;

// EXPECT-NONE: a composition root is not a service by this rule's test, for any
// host. The exemption used to be a list naming one installation's classes, which
// implied the privilege belonged to that host rather than to the shape of the
// boundary.
final class Bootstrap
{
    public static function run(): void
    {
    }

    public static function services(): string
    {
        return 'container';
    }
}

function boot(): void
{
    Bootstrap::run();
    Bootstrap::services();
}
