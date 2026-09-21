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
