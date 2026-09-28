<?php
declare(strict_types=1);

namespace Iniznet\Mahout\Fixture;

/**
 * A file no classmap entry points at. It is out of scope by design: the gate audits the
 * completeness of entries the map already claims, which is how a third-party tree that
 * autoloads by PSR-4 instead of by classmap is never mistaken for a stale map.
 */
final class Unreferenced
{
}
