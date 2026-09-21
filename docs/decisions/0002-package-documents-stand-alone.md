# ADR-0002: package documents link nothing into the private corpus

- **Status:** Accepted
- **Date:** 2026-01-01
- **Supersedes:** nothing

## Context

The contribution contract tells a package `README.md` to link the canonical
planning corpus, and the discipline contract `AGENTS.md` to point at the
planning documents. The corpus is excluded from every published repository by an
explicit owner decision, and every `.gitignore` carries a `/docs/planning/`
rule with a comment saying so.

A link into an excluded directory is a link that 404s for every person who
clones this repository. It is worse than no link: the reader concludes the
document is missing rather than private.

## Decision

Every document in this repository — `README.md`, `AGENTS.md`,
`CONTRIBUTING.md`, `SECURITY.md` and the issue and pull request templates —
stands alone and contains no link into `docs/planning/`. The discipline
contract is restated in full rather than linked. Package-scoped decisions live
under `docs/decisions/` and are linked, because those files are published with
the repository.

## Consequences

- The README's Architecture section describes the package and points at
  `docs/decisions/`, not at the corpus.
- `AGENTS.md` carries the contract's content, so a contributor from a package
  search result is not sent to a document they cannot open.
- The restatement is a copy, and a copy can drift. The mitigation is that the
  corpus is private and therefore cannot be linked; this is the only coherent
  reading.
- This applies to every package a later slice creates.
