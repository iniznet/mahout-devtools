<?php

/**
 * `innodb_buffer_pool_size` sized to the working set is the last declared
 * production prerequisite. Everything else in the cost model assumes the data a
 * request reads is already resident; when it is not, the same query costs disk
 * instead of 0.3 ms and the ceiling in the model stops describing the machine.
 *
 * The working set is read from this installation's own table statistics rather
 * than from a rule of thumb, so the number is derived rather than assumed. The
 * schema query is legitimate here and nowhere else: `doctor` is a command a human
 * or a deploy step runs, never a request path, which is what the contract's ban on
 * schema queries is about.
 *
 * No credential is ever reported. The failure path carries the driver's message,
 * which names a socket or a host — not a password.
 */

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor\Checks;

use Iniznet\Mahout\Devtools\Console\DatabaseCredentials;
use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;
use Iniznet\Mahout\Devtools\Doctor\InnoDBStatistics;

final readonly class InnoDBBufferPoolCheck implements Check
{
    /**
     * @param InnoDBStatistics|null $measured the statistics, when the caller already has them;
     *                                        a test seam, because a unit test must not need a
     *                                        running database to prove the comparison
     */
    public function __construct(
        private string $wordpressRoot,
        private ?InnoDBStatistics $measured = null,
    ) {
    }

    public function name(): string
    {
        return 'InnoDB buffer pool';
    }

    public function examine(): CheckResult
    {
        $config = '' === $this->wordpressRoot ? '' : $this->wordpressRoot.'/wp-config.php';

        if ('' === $config || !is_file($config)) {
            return CheckResult::skip($this->name(), 'no wp-config.php at this root; the site database is not reachable from here');
        }

        $statistics = $this->measured;

        if (null === $statistics) {
            $resolved = $this->connect($config);

            if ($resolved instanceof CheckResult) {
                return $resolved;
            }

            $statistics = $resolved;
        }

        if (!$statistics->fits()) {
            return CheckResult::fail(
                $this->name(),
                sprintf('innodb_buffer_pool_size is %s but this site\'s tables occupy %s: the working set does not fit, so reads go to disk', $statistics->poolMegabytes(), $statistics->workingMegabytes()),
            );
        }

        return CheckResult::pass(
            $this->name(),
            sprintf(
                'pool %s >= working set %s%s',
                $statistics->poolMegabytes(),
                $statistics->workingMegabytes(),
                0.0 === $statistics->headroom() ? '' : sprintf(' (%sx headroom)', number_format($statistics->headroom(), 1)),
            ),
        );
    }

    /**
     * Either the measurement, or the result that says why there is none.
     */
    private function connect(string $config): InnoDBStatistics|CheckResult
    {
        $credentials = DatabaseCredentials::fromConfigFile($config);

        if (null === $credentials) {
            return CheckResult::fail($this->name(), 'wp-config.php declares no database name; the working set cannot be measured');
        }

        // A failed connect raises a diagnostic or an exception depending on the
        // mysqli report mode in force, and WordPress's own test bootstrap sets it to
        // OFF. Both paths end in the same reported result: the exception below, the
        // connect error here. The suppression covers the diagnostic half only.
        try {
            $connection = @new \mysqli($credentials->host, $credentials->user, $credentials->password, $credentials->name, $credentials->port);
        } catch (\mysqli_sql_exception $exception) {
            return CheckResult::fail($this->name(), 'the site database is unreachable: '.$exception->getMessage());
        }

        if (0 !== $connection->connect_errno) {
            return CheckResult::fail($this->name(), sprintf('the site database is unreachable: (%d) %s', $connection->connect_errno, $connection->connect_error));
        }

        $statistics = InnoDBStatistics::fromConnection($connection);
        $connection->close();

        return $statistics ?? CheckResult::fail($this->name(), 'the pool size or the table statistics could not be read');
    }
}
