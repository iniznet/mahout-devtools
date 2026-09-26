# ADR-0007 — The gate set is a manifest, not a convention

Status: accepted

## Context

Every repository in the family declares the same `scripts` block: `format`,
`stan`, `psalm`, `arch`, `rector`, `test`, `hooks:check`, `i18n:check`,
`doctor`, `config:check`, and a `check` event that runs them. The contract
lists them as gates, so law 5 requires a gate that enforces them — and there
was none. Repetition by convention drifted silently: one package's `check`
never ran the hook or translation gate, and eight ran the architecture rules
as a second invocation of the identical PHPStan analysis because the rules
ship inside the shared configuration that `stan` already loads. Both were
found by hand, which is the failure mode the contract refuses.

Composer offers no inheritance for `scripts`: a consumer cannot include or
extend another package's script set, so the only alternatives are copying —
which is the divergence the family already forbids for analyzer configuration
— or checking a copy against a declared truth.

## Decision

The gate set is declared in this package at `resources/gates.json`, and
`composer config:check` diffing each consumer against it through
`GatesManifestCheck`. Two placeholders let one manifest serve the whole family,
and both are resolved from the repository under examination rather than
declared by it:

- `{domain}` is the last element of its package name, which is also its text
  domain and artefact name;
- `{source}` is the first path its own `phpstan.neon` analyses, so a gate can
  never scan a different tree than the analyzer scans.

A repository may declare scripts beyond the set. It may not omit a canonical
gate, redefine one's command, drop one from its `check` event, run the event
out of order, or run a step the manifest does not know. `optional` is where a
package-specific gate is declared; adding one to a `check` event without
declaring it fails.

## Consequences

Changing a gate for the family is a contract change in the sense the
contribution rules use: the manifest in this package moves first, consumers
adopt, and a consumer that moves alone fails its own build. The cost is that
`resources/gates.json` now carries a little truth about every repository; the
alternative was that each repository carried a copy of the same truth and no
one could tell when they disagreed.
