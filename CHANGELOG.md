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
- The divergence check inside `composer config:check`: a consumer references
  the analyzer configuration and architecture rules shipped by the pinned
  `mahout-devtools`, and carries no local copy. Proven against the fixture
  consumers under `fixtures/divergence/`, including the failing cases.
