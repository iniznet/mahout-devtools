<?php
declare(strict_types=1);

namespace Iniznet\Mahout\Fixture;

final class Present
{
}

/**
 * A second type in a file the map already references. Composer's optimised dump puts
 * every class it finds in a classmapped file into the map, so a file the map points at
 * whose declarations the map does not list is a map that predates the source.
 */
final class AddedAfterTheDump
{
}
