<?php

/**
 * A fixed-concurrency probe against a running installation.
 *
 * It exists because every capacity number in the throughput model is arithmetic
 * on a per-request cost that had never been measured on the real server. The
 * probe measures; it does not judge. Absolute numbers never fail the run — a
 * slower machine is not a broken theme — so only transport and status failures
 * set an exit code, which is what makes the smoke job safe to keep in the
 * pipeline instead of deleting after its first red day.
 *
 * `curl_multi` is used rather than a load tool for one reason: it is a core PHP
 * extension, so the probe travels with this package and adds no dependency to a
 * consumer's `require-dev`, which the contract keeps to our own packages plus
 * the analysis toolchain.
 */

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Probe;

final readonly class LoadProbe
{
    private const float GIVEUP_SECONDS = 30.0;

    public function __construct(
        private string $url,
        private int $concurrency,
        private int $requests,
    ) {
    }

    public function run(): ProbeSummary
    {
        $multi = curl_multi_init();
        $started = [];
        $issued = 0;
        $failed = 0;
        $drained = 0;
        $timings = [];
        $pending = $this->requests;
        $running = 0;
        $began = microtime(true);

        // Each slot is one connection: the concurrency here is the same number the
        // deployment declares for the pool, because a probe that opens more workers
        // than exist measures the queue rather than the site.
        $add = function () use ($multi, &$started): bool {
            $handle = curl_init($this->url);

            if (false === $handle) {
                return false;
            }

            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => (int) self::GIVEUP_SECONDS,
                CURLOPT_FOLLOWLOCATION => false,
            ]);

            $started[spl_object_id($handle)] = microtime(true);
            curl_multi_add_handle($multi, $handle);

            return true;
        };

        do {
            while ($pending > 0 && $running < $this->concurrency) {
                if ($add()) {
                    ++$running;
                } else {
                    ++$failed;
                }

                --$pending;
                ++$issued;
            }

            $active = $running;
            $status = curl_multi_exec($multi, $active);

            while (false !== ($info = curl_multi_info_read($multi))) {
                $handle = $info['handle'] ?? null;

                if (!$handle instanceof \CurlHandle) {
                    continue;
                }

                $key = spl_object_id($handle);
                $elapsed = (microtime(true) - ($started[$key] ?? microtime(true))) * 1000.0;
                $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

                curl_multi_remove_handle($multi, $handle);
                curl_close($handle);
                unset($started[$key]);
                --$running;
                ++$drained;

                if (CURLE_OK === $info['result'] && $code >= 200 && $code < 400) {
                    $timings[] = $elapsed;
                } else {
                    ++$failed;
                }
            }

            if ($running > 0) {
                curl_multi_select($multi, 0.05);
            }
        } while (($running > 0 || $pending > 0) && CURLM_OK === $status);

        // Every issued request is either drained or counted as failed; a transfer
        // that leaves neither way would silently understate the site's error rate,
        // so the arithmetic is asserted rather than assumed.
        if ($issued !== $drained + \count($started)) {
            $failed += $issued - $drained - \count($started);
        }

        $failed += \count($started);

        curl_multi_close($multi);

        return ProbeSummary::fromRun($issued, $failed, microtime(true) - $began, $timings);
    }
}
