# 0004 — The analyzer toolchain lives in this tool's own require

Status: accepted.

## Context

Composer does not install a dependency's `require-dev` into a consumer. A package that a consumer
installs receives that package's `require`, and nothing else.

This tool pins the analyzer toolchain — PHPStan, Psalm, Rector, PHP-CS-Fixer, PHPUnit and the
polyfills — because it ships the configuration those tools read and the rules PHPStan loads. If those
pins stay in this tool's `require-dev`, they reach nobody, and every consumer is forced to re-pin all
six itself. Six packages re-pinning six tool versions is six opportunities for the family to diverge,
which is the failure the reference gate exists to prevent.

## Decision

The toolchain is declared in `require`, not `require-dev`, of `iniznet/mahout-devtools`.

It is still developer-time only. `15-operations-and-conventions.md` §Consumption has every consumer
declare `iniznet/mahout-devtools` in its own `require-dev`, so `composer install --no-dev` — the
production install — excludes this tool and therefore excludes the whole toolchain.

A consumer's `require-dev` consequently lists exactly one package: `iniznet/mahout-devtools`. That is
what makes the Phase 1 exit criterion "require-dev lists only our own packages" true for every package
in the family.

## Consequence, stated plainly

This tool's `require` is not empty, so the Phase 1 exit criterion "composer.json require is empty"
does not hold literally. It cannot hold simultaneously with "a consumer's require-dev lists only our
own packages", because one of the two has to carry the toolchain and only this tool's `require` can
make it transitive. The criterion is read as its intent: no third-party library reaches a consumer's
runtime. Nothing in this tool's `require` is loaded on a request path.

The toolchain version pins now have exactly one home. Changing a tool version is one edit in one
repository, not seven.

## The measured state of the PHPUnit pin

Recorded 2026-07-09, while reviewing whether to move the family to PHPUnit 11.

PHPUnit 9.6 is end-of-life upstream, which is a reason to consider the move. It is not a cost, and the
usual forcing function is absent: the two heaviest suites in the family run clean on PHP 8.4.20 with no
deprecation output whatsoever — 127 tests in the theme, whose integration suite drives a live WordPress
7.1.2 test harness, and 265 in `mahout-fields`.

The blocker is not ours either. WordPress core's own test suite pins `yoast/phpunit-polyfills` at
`^1.1`, and the polyfills series maps onto PHPUnit support: 1.x covers PHPUnit 4.8–9.x, 3.x covers
6.4–11.x. Core's bootstrap enforces only a *minimum*, so the family could run polyfills 3.x with
PHPUnit 11 today — but it would be running core's `WP_UnitTestCase` on a combination core does not test
itself. Driving a dependency beyond its tested surface, because a version guard happens to permit it,
is the degraded-mode failure law 3 refuses.

The pin therefore stays deliberately, and the move is a WordPress-core event rather than a family one.
When core raises its polyfills constraint, the change is this file, the `phpunit.xml.dist` schema in each
consumer, and `static` data providers in the suites. The version itself keeps exactly one home, which is
the whole point of this decision.
