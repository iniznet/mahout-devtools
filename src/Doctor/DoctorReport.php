<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor;

/**
 * The ordered result of a doctor run.
 */
final readonly class DoctorReport
{
    /**
     * @param list<CheckResult> $results
     */
    public function __construct(public array $results)
    {
    }

    public function failureCount(): int
    {
        $count = 0;
        foreach ($this->results as $result) {
            if ($result->status->isFailure()) {
                ++$count;
            }
        }

        return $count;
    }

    public function warningCount(): int
    {
        $count = 0;
        foreach ($this->results as $result) {
            if (Status::Warn === $result->status) {
                ++$count;
            }
        }

        return $count;
    }

    public function exitCode(): int
    {
        return $this->failureCount() > 0 ? 1 : 0;
    }

    /**
     * Render every result as a fixed-width table.
     */
    public function toTable(): string
    {
        $headers = ['STATUS', 'CHECK', 'DETAIL'];
        $rows = [];

        foreach ($this->results as $result) {
            $rows[] = [$result->status->value, $result->name, $result->detail];
        }

        $widths = [strlen($headers[0]), strlen($headers[1])];
        foreach ($rows as $row) {
            $widths[0] = max($widths[0], strlen($row[0]));
            $widths[1] = max($widths[1], strlen($row[1]));
        }

        $format = '%-'.$widths[0].'s  %-'.$widths[1].'s  %s';

        $output = sprintf($format, $headers[0], $headers[1], $headers[2]).PHP_EOL;
        foreach ($rows as $row) {
            $output .= sprintf($format, $row[0], $row[1], $row[2]).PHP_EOL;
        }

        return $output;
    }
}
