<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Probe;

/**
 * What a probe run observed. Percentiles are computed over completed requests
 * only: a failed request has no latency worth reporting, and averaging it in
 * would hide the failure inside a healthy-looking number.
 */
final readonly class ProbeSummary
{
    /**
     * @param list<float> $timings latency in milliseconds, any order
     */
    private function __construct(
        public int $issued,
        public int $completed,
        public int $failed,
        public float $wallSeconds,
        private array $timings,
    ) {
    }

    /**
     * @param list<float> $timings
     */
    public static function fromRun(int $issued, int $failed, float $wallSeconds, array $timings): self
    {
        return new self($issued, \count($timings), $failed, $wallSeconds, $timings);
    }

    public function requestsPerSecond(): float
    {
        if ($this->wallSeconds <= 0.0) {
            return 0.0;
        }

        return $this->completed / $this->wallSeconds;
    }

    public function percentile(float $fraction): float
    {
        if ([] === $this->timings) {
            return 0.0;
        }

        $sorted = $this->timings;
        sort($sorted);
        $index = (int) ceil($fraction * \count($sorted)) - 1;

        return $sorted[max(0, min($index, \count($sorted) - 1))];
    }

    public function mean(): float
    {
        return [] === $this->timings ? 0.0 : array_sum($this->timings) / \count($this->timings);
    }

    public function toLine(): string
    {
        return sprintf(
            'issued=%d completed=%d failed=%d wall=%.2fs rps=%.1f mean=%.1fms p50=%.1fms p95=%.1fms p99=%.1fms',
            $this->issued,
            $this->completed,
            $this->failed,
            $this->wallSeconds,
            $this->requestsPerSecond(),
            $this->mean(),
            $this->percentile(0.50),
            $this->percentile(0.95),
            $this->percentile(0.99),
        );
    }
}
