<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor;

/**
 * One immutable doctor observation: a named check, its status and its detail.
 */
final readonly class CheckResult
{
    private function __construct(
        public string $name,
        public Status $status,
        public string $detail,
    ) {
    }

    public static function pass(string $name, string $detail = ''): self
    {
        return new self($name, Status::Pass, $detail);
    }

    public static function warn(string $name, string $detail = ''): self
    {
        return new self($name, Status::Warn, $detail);
    }

    public static function fail(string $name, string $detail = ''): self
    {
        return new self($name, Status::Fail, $detail);
    }

    public static function skip(string $name, string $detail = ''): self
    {
        return new self($name, Status::Skip, $detail);
    }
}
