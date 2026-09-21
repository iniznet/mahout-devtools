<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Exception;

/**
 * Package marker for every exception this package throws.
 *
 * A consumer that does not know an individual condition can still catch the
 * package's failures without catching anything from another package.
 */
interface DevtoolsException extends \Throwable
{
}
