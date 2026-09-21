# ADR-0001: the analyzer toolchain is a genuine dev dependency

- **Status:** Accepted
- **Date:** 2026-01-01
- **Supersedes:** nothing

## Context

The delivery roadmap's Phase 1 exit criterion says the package's `require` is
empty and `require-dev` lists only our own packages. The consumption rule in the
operations document says every *other* package declares `mahout-devtools` in
`require-dev`, which is what makes that sentence true for them.

`mahout-devtools` supplies the analyzer configuration. It cannot configure a
tool it does not ship. PHPStan, Psalm, Rector, PHP-CS-Fixer and PHPUnit are
third-party artefacts; the architecture rules and the shared configuration are
the package's own code, but the tools that consume them are not.

The alternatives are worse:

- **Vendor the tools.** Committing a vendor tree contradicts the repository
  policy, which says `vendor/` is ignored and reproduced from the lockfile.
- **Leave the tools undeclared.** The gates would resolve whatever the machine
  happens to have, so a lockfile would no longer pin the toolchain and two
  machines would run different rule sets.
- **Make the tools plugins of another package.** It hides a dependency behind a
  transitive resolution and violates explicit-over-implicit.

## Decision

`mahout-devtools` declares its analyzer and test toolchain as third-party
`require-dev` dependencies, pinned in the committed lockfile. `require` stays
empty: nothing this package ships is needed at runtime.

## Consequences

- The Phase 1 wording holds for every other package, because they declare
  `mahout-devtools` in `require-dev` and receive the toolchain transitively.
- The toolchain version a repository enforces is a consequence of its lockfile,
  not of a local edit, which is the property the divergence rule depends on.
- This package is the one exception to the literal wording, and the exception is
  recorded here rather than by editing the shared contract.
