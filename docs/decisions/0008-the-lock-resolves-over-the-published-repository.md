# ADR-0008 — The lock resolves over the published repository

Status: accepted; supersedes the lockfile clause of each package's shared-config ADR

## Context

REP-11 banned a committed `path` repository in `composer.json`, and every package
obeyed: the sibling path override lives in the uncommitted `composer.dev.json`.
What the rule did not cover was `composer.lock`. A lock written by a path install
records `"dist": {"type": "path", "url": "../mahout-kernel"}` for every family
package, so the committed lock pointed at directories that exist only on the
machine that wrote it.

The consequence was not cosmetic. Cloning `iniznet/mahout-kernel` and running
`composer install` failed with `Source path "../mahout-devtools" is not found`,
which meant no consumer, no fork and no CI runner could install any package in the
family, and the contribution contract's promise that a fork pull request runs the
same `composer check` was unsatisfiable. Each package recorded this as an accepted
temporary deviation on the ground that `mahout-devtools` was not published yet —
after publication the repositories were pushed and the ground went away while the
locks, and the prose asserting the deviation, stayed.

## Decision

Every repository in the family declares its family requirements as committed VCS
repositories against `github.com/iniznet`, and its lock resolves over them. The
`path` repository remains exactly where REP-11 put it: uncommitted, in
`composer.dev.json`, used by a developer who works on siblings side by side.

REP-11 now covers both halves of the same fact. `NoPathRepositoryRule` sees the
manifest from a file node; `PathRepositoryCheck` additionally reads
`composer.lock` and fails any package pinned to a `path` dist, because that is
the half that decides whether a fresh clone can install.

## Consequences

A fresh clone installs, so a fork and CI work. The dist URLs GitHub hands out are
`api.github.com/.../zipball`, which count against the unauthenticated request
budget — sixty an hour per address — so a runner that resolves the family often
should set `GITHUB_TOKEN` or `composer config --global --auth
github-oauth.github.com`. That is an operational note, not a regression: an
unauthenticated install of one repository costs a handful of requests.

Because a consumer's lock can now name a published commit, a change in one
package reaches another only when the second updates its lock. That is the normal
cost of portable dependencies and the reason the family tags in dependency order:
kernel, then assets, db and content, then fields, then the theme.
