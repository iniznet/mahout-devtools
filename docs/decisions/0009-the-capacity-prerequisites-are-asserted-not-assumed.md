# ADR-0009 — The capacity prerequisites are asserted, not assumed

Status: accepted

## Context

The contract lists four production prerequisites — OPcache enabled with
`validate_timestamps=0`, a preload file, an autoloader generated with
`--classmap-authoritative --optimize`, and an `innodb_buffer_pool_size` sized to
the working set — and states that none is required for correctness but all are
required for the declared capacity numbers to hold. The required-test table names
the failures: `doctor` failing on OPcache off, on a missing preload file, on a
non-authoritative autoloader.

None of it existed. `doctor` ran ten checks, every one of them about the analysis
toolchain or the package's own files, and not one about the machine that serves
traffic. The throughput model meanwhile rested on a per-request cost of 7.0 ms that
had never been measured, and no instrument existed to measure it with.

That is law 5 in its exact shape: a rule that exists only in a markdown file does
not exist. And the omission had a cost with a number on it — measuring the
installation for the first time found 9,777 PHP files that a request can load
against a declared `opcache.max_accelerated_files` of 10,000, a two percent margin
on a development box that had never been able to notice it narrowing.

## Decision

`doctor` asserts the prerequisites, through four checks, and the family ships a
load probe to measure what the model assumed.

**Three outcomes, chosen by whether the finding is a defect.** A prerequisite wrong
in every mode fails: OPcache disabled, no preload file, a classmap that is not
authoritative, a pool smaller than the data it must hold. A prerequisite that is
correct on a development box and wrong in production is judged against the
installation's own declaration, `WP_ENVIRONMENT_TYPE`, read as text from
`wp-config.php`: declared production fails on `validate_timestamps` and on a pool
under the declared 256 MB, any other declaration warns with the remedy in the same
line, and an installation that declares nothing is told that the production-only
assertions are not being made. Failing a developer for developing produces a gate
that gets deleted, which is worse than no gate; guessing which mode a site is in
produces a gate that passes in the wrong direction. A check that cannot
apply to the root it was given is reported as skipped, which is what `doctor` already
does for a WordPress version or a workflow directory. The tiers are recorded because
the difference between them is the difference between a gate that survives and one
that gets routed around.

**Probes run in a child process.** The installed classmap is inspected and the
preload script is executed by a separate `php`, never inside `doctor` itself: a
diagnostic that loads the site's autoloader into its own process has stopped
measuring the deployment and started being part of it. The child also proves the
two things that only happen at pool start — that the classmap answers
`isClassMapAuthoritative()`, and that a preload file survives being preloaded.

**Numbers are derived from the installation, not written down as constants.** The
opcode-cache floor is counted across `wp-includes`, `wp-admin`, `wp-content/plugins`
and `wp-content/themes`; the working set is read from the site's own table
statistics. Both were the shape of assumption the model was trying to retire.

**The check says what the platform cannot answer.** `opcache.preload` does not exist
on Windows: PHP refuses it at startup. There the check asserts the file parses and
reports that pool start cannot be exercised from that machine. This is not the
environment-dependent behaviour switch the contract forbids, which is about the
*theme* changing shape per cache layer; it is a diagnostic declining to claim a
verification it did not perform.

**The probe measures and does not judge.** `load:probe` issues a fixed concurrency
through `curl_multi` — a core extension, so it adds no dependency to a consumer's
`require-dev` — and reports p50, p95, p99 and requests per second. Only a request
that does not return a 2xx or 3xx sets an exit code. A smoke job that fails because
a machine is slow is a smoke job that gets removed after its first red day, so
absolute latency is reported and never asserted.

## Consequences

The dev-loop gate stays green on a development box, and the report now carries the
four facts the capacity model depends on.

One of the two numbers this change produced replaces an assumption: the file floor is
a count of files on disk, it does not care which SAPI counted them, and at 9,777
against a declared 10,000 it is thin in a way nobody had looked at. `doctor` will
report the moment it is exceeded.

The other number does not replace anything, and saying so is part of the record. The
first probe runs measured about 106 ms at concurrency one on the local installation.
That is not a measurement of the model's 8.8 ms per request and it must not be read
as twelve times anything: the local stack is nginx talking to `php-cgi` on a
thread-safe Windows build, so the figure contains the per-request process cost that a
resident pool worker never pays, on a machine with timestamp validation on, no object
cache and no page cache. It measures the rig, not the theme.

A2 therefore remains an assumption until the probe runs against a Linux pool with the
prerequisites asserted — the same condition under which `PreloadFileCheck` can exercise
pool start at all, since `opcache.preload` does not exist on Windows, and under which
`--classmap-authoritative` is an install shape rather than a local override. This
workstation is a Windows host under Laragon with no container runtime and no
subsystem, so neither condition is reachable here: the production-shaped measurement
comes from a Linux runner or the real host, and nothing measured on this box belongs
in the model's arithmetic. Because mysqli's report mode differs between a WordPress
bootstrap and a bare CLI, the unreachable-database path handles both the exception and
the connect error rather than betting on one.
