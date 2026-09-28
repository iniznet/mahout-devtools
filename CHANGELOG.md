# Changelog

All notable changes to this project are documented here, in the order
recommended by [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

The project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
A major entry names each removal.

## [Unreleased]

### Added

- The package skeleton: contracts, doctor, checks, stub generator and the
  command-line entry point.
- Self-generated WordPress stubs derived from the installed core, with a
  byte-identical regeneration gate.
- The four shared analyzer configuration files.
- The WordPress test bootstrap, base test case and the custom-table isolation
  proof.
- The `doctor` command with its Phase 1 check set.
- The architecture rules behind `composer arch`, each registered from
  `phpstan.neon` and shipped with a positive and a negative fixture under
  `fixtures/architecture/`, asserted in one PHPStan pass by
  `tests/Architecture/ArchitectureRulesTest.php`.
- The POT generator behind `composer i18n:check` and `composer i18n:generate`.
  The output is deliberately date-free, so a second run is byte-identical and a
  stale template fails the gate.
- The hook-reference generator behind `composer hooks:check` and
  `composer hooks:generate`.
- `TransactionOnlyInGatewayRule::STATEMENT_PATTERN`, the single definition of
  "this literal is a transaction statement", so a consumer's own scan reads the
  same pattern the gate does instead of repeating it.
  the analyzer configuration and architecture rules shipped by the pinned
  `mahout-devtools`, and carries no local copy. Proven against the fixture
  consumers under `fixtures/divergence/`, including the failing cases.

### Changed

- `hooks:check` and `hooks:generate` write two documents, split by hook kind:
  `<outdir>/actions.md` and `<outdir>/filters.md`. A single mixed table asked
  the reader to filter rows mentally for the question they actually came with
  — which hooks fire and forget, versus which hooks return a value — and the
  kind is the distinction the generator already records from the docblock. The
  scan stays in `HooksReference` and runs once; `HookDocument` renders one kind,
  and `HookType` owns the file names, the headings and the gate message, so a
  consumer's composer script names a directory rather than two files.
- A kind with no hooks still gets a document, and both documents are gated on
  every run. A gate that only ever compares a file that exists when there is
  something to say stops running the day a package deletes its last filter.

### Removed

- The `--output` option of `hooks:check` and `hooks:generate`, replaced by
  `--outdir`. One artefact became two, so a single file path no longer names
  what the command writes; keeping both spellings would leave a consumer's
  script pointing at a file that is never produced. Adopting consumers run
  `hooks:generate --source=<own source> --outdir=docs/reference`, commit
  `docs/reference/actions.md` and `docs/reference/filters.md`, and delete
  `docs/reference/hooks.md`. This removal is why the release is 2.0.0.

### Fixed

- `BoundedHowdahStatementRule` is no longer silent on every real table name.
  It matched `/\bhowdah\w*\b/`, which cannot match the `{$wpdb->prefix}`
  form, and it read only scalar string literals; it now matches a project table
  in a statement's table position — `howdah_<entity>` and `mahout_<name>`,
  each behind a configured prefix — and reads interpolated and concatenated
  statements. Three isolated fixtures prove it fires: a `wptests_`-prefixed
  name, the `"{$wpdb->prefix}..."` form, and a package table.
- `TransactionOnlyInGatewayRule` matches the keyword in statement position
  instead of anywhere in a literal, so an exception message that explains a
  rollback is prose rather than a violation. A fixture with prose is clean; two
  fixtures with real statements fire.
- `CapabilityLiteralOnlyInCapabilitiesRule` no longer exempts code inside a
  class named `Capabilities`. A capability is a typed constant on the package's
  `Capabilities` type and the check site names it; a literal at the check site
  is a violation wherever it is written.

### Decisions

- 0005: a statement is matched where SQL puts it, not where the letters fall.
- 0006: a capability is declared on a Capabilities type, and a literal is never
  the check.
