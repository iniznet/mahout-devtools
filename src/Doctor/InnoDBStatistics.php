<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor;

/**
 * The two numbers the buffer-pool assertion is about, read from the server that
 * is actually running: how much pool it was given, and how much of the site's
 * own data that pool has to hold.
 *
 * Kept apart from the check so the comparison can be tested without a database,
 * and so the check itself never carries a query.
 */
final readonly class InnoDBStatistics
{
    private function __construct(
        public int $poolBytes,
        public int $workingBytes,
    ) {
    }

    public static function of(int $poolBytes, int $workingBytes): self
    {
        return new self($poolBytes, $workingBytes);
    }

    /**
     * `null` when either number could not be read; the caller reports that rather
     * than comparing against a zero it did not measure.
     */
    public static function fromConnection(\mysqli $connection): ?self
    {
        $pool = self::scalar($connection, 'SELECT @@innodb_buffer_pool_size');
        $working = self::scalar(
            $connection,
            'SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()',
        );

        if (null === $pool || null === $working) {
            return null;
        }

        return new self($pool, $working);
    }

    public function fits(): bool
    {
        return $this->poolBytes >= $this->workingBytes;
    }

    public function headroom(): float
    {
        if (0 === $this->workingBytes) {
            return 0.0;
        }

        return $this->poolBytes / $this->workingBytes;
    }

    public function poolMegabytes(): string
    {
        return self::megabytes($this->poolBytes);
    }

    public function workingMegabytes(): string
    {
        return self::megabytes($this->workingBytes);
    }

    private static function scalar(\mysqli $connection, string $query): ?int
    {
        $statement = $connection->query($query);

        if (!$statement instanceof \mysqli_result) {
            return null;
        }

        $row = $statement->fetch_row();
        $statement->free();

        if (null === $row || !isset($row[0]) || !is_numeric($row[0])) {
            return null;
        }

        return (int) $row[0];
    }

    private static function megabytes(int $bytes): string
    {
        return number_format($bytes / 1048576, 1).' MB';
    }
}
