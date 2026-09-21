<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Tests;

use WP_UnitTestCase;

/**
 * The base test case for the mahout family.
 *
 * Core's WP_UnitTestCase already wraps each test in a transaction and provides
 * the factories. This class exists so a package extends one name, and so later
 * slices have a single place to add the shared helpers the corpus requires.
 *
 * @internal
 */
abstract class TestCase extends \WP_UnitTestCase
{
}
