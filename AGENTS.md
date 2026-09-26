# AGENTS.md — the mahout discipline contract

This is the working contract for `iniznet/mahout-devtools` and, by reference,
for every repository in the mahout family. It is a summary, not the authority:
where it disagrees with a recorded decision, the decision wins and this file is
corrected.

**Stack:** WordPress 7.1+ · PHP 8.4+ · PHPUnit · PHPStan (max) · Psalm (taint) ·
Rector · PHP-CS-Fixer · Composer.

## The six laws

1. **Explicit over implicit.** Every dependency, hook, registration and side
   effect is greppable from the composition root.
2. **One way to do a thing.** No alternative paths kept "just in case".
3. **Fail fast and loud.** No silent fallback, no degraded mode, no
   environment-dependent behaviour switch.
4. **Small public surface.** `Contracts/` plus a documented handful of concrete
   classes. Everything else is `@internal`.
5. **Tooling enforces what prose promises.** A gate that exists only in a
   markdown file does not exist.
6. **Layer neutrality.** A generated theme is correct and complete with no
   object cache, no page cache and no CDN, and faster as each is added, with no
   configuration change and no code change.

## Where code goes

| Layer | Location | Owns | Must not |
|---|---|---|---|
| Domain | `app/Features/<Name>/` | queries, repositories, schema, hooks, rules | render HTML |
| Presentation | `app/Components/` | rendering typed props to HTML | fetch data, touch globals, fire hooks |
| Composition | `app/Render/` | resolving a request to a Surface | contain domain rules |
| Infrastructure | `app/Providers/` | hooks, assets, REST, admin wiring | contain domain rules |

Arrows point one way only:

```
Providers --> Modules --> Repositories --> Mapper --> Data (DTO)
                              |
                              v
Surfaces --> Components --> Data (DTO)
```

A Component never imports a repository. A Repository never imports a Component.

## The six-step feature recipe

1. Declare data in `<Feature>Schema.php`, with an explicit `StorageTarget`
   per field.
2. Register the module — one line in the composition root.
3. Write the query in `<Feature>Repository.php`; prime caches there.
4. Map to a DTO in `<Feature>Mapper.php`.
5. Compose the page with a Surface and Components.
6. Test: a unit test per component, integration tests for the repository and
   the Surface's query ceiling.

## Banned constructs

- `extract()`, `__get`, `__set`, `__call`, dynamic properties.
- `meta_query` in a public API; `posts_per_page => -1`.
- Raw hook-name strings; hook names are `public const` on a `Hooks` class.
- `get_post_meta()` / `get_user_meta()` on a registered field.
- Components calling repositories, or referencing `WP_*` types.
- `template_include` routing; `WP_List_Table` subclasses; quick-edit writes.
- Reflection, service locators and facades; static access to a service.
- Trait properties, and `$this->` from a trait calling an undeclared member.
- `new \Exception(...)` or a public exception constructor.
- `error_log()` outside `Diagnostics`.
- Superglobals outside `Iniznet\Howdah\Support\Request`.
- Inline `<script>` or `<style>` echo.
- PHPStan baselines, and `@phpstan-ignore` without a reason.
- `mixed` where a union is expressible.
- `START TRANSACTION`, `COMMIT` or `ROLLBACK` outside the database gateway.
- `wp_cache_flush()`; `setcookie()` / `setrawcookie()`.
- A canonical, `robots` or `description` meta tag, and hand-set security headers.
- A dispatch arm without a `Cacheability` declaration.
- An unbounded statement against a howdah table.
- `sleep()`, `usleep()`, `set_time_limit()`, or a wait-for-lock loop.

## Static access

**Permitted:** named constructors and stateless codecs, enums, and
`*::class` constants.

**Banned:** static access to anything that queries, caches, mutates or resolves
a collaborator. The test is not "is it static" but "does it resolve a
collaborator".

## Conventions

- `final` by default; `#[Override]` on every override; named arguments for
  optional parameters; no boolean flags.
- DTOs are `final readonly`, promoted, fully typed, `list<T>` in docblocks.
  No `toArray()`, no `ArrayAccess`.
- Value objects enforce invariants with PHP 8.4 property hooks.
- Exceptions are `final`, privately constructed, with static named
  constructors, extending the most specific SPL exception.
- Expected absence returns `?T`. Broken invariants throw.

## Hooks

Names are `mahout/{package}/{event}` for a library and
`howdah/{domain}/{event}` for the theme, and are `public const` on a `Hooks`
class. Actions never return; filters return the first argument. Filters pass
values and arrays, never mutable WordPress objects. Hooks are emitted only from
Providers and Modules.

## Storage

`wp_postmeta` is a load-with-the-entity store, not a query store. A field is
`Meta` when read with the entity and never filtered; `Table` when filtered,
sorted, aggregated or counted. `storage` is required on every field; there is
no default. A `Table` field is written only through the field panel or the
field REST route. Sensitive values go in constants or environment only.

## Security

Request input has one boundary: superglobals are read only inside
`Iniznet\Howdah\Support\Request`, injected through constructors. A
state-changing request uses POST, a verified nonce and a capability check. A
REST route without an explicit `permission_callback` fails the build.
Sanitize on write, escape on read, exactly once per output.

## Errors

A thrown exception is recorded through `Diagnostics` at `critical`.
Development rethrows it; production renders the Error Surface with status
`500`. No silent fallback, no substituted data, no white screen.

## Quality gates

```bash
composer format      # PHP-CS-Fixer
composer stan        # PHPStan, max level, no baseline
composer psalm       # Psalm taint analysis
composer arch        # architecture rules
composer rector      # Rector dry-run
composer test        # PHPUnit
composer hooks:check # generated hook reference is current
composer i18n:check  # generated POT is current
composer doctor      # installation assembly and the capacity prerequisites
composer config:check# divergence and the artifact set
composer check       # all of the above, in order
```

`composer check` must pass before every commit, with no `--no-verify`. A gate
that cannot run fails loudly; it never passes silently. The two generated
references are refreshed with `composer hooks:generate` and
`composer i18n:generate` when a hook or a translation string changes.

## Required tests

Every component's rendered output; every value object's invariant; every
exception's named constructor; every repository query shape; field round-trips;
every Surface's query ceiling; every dispatch arm's declared `Cacheability`;
byte-identical output for two anonymous visitors on a `Shared` Surface;
single-flight; the doctor's failure paths; and a positive and a negative fixture
for every architecture rule.

## Cacheability and throughput

Every Surface declares a `Cacheability` and a `FragmentScope`, with a stated
reason on every `Uncacheable` arm. No cache layer is required and none changes
the code. Invalidation emits a purge event; no vendor API is called. Every
statement is bounded by a `LIMIT` or a primary-key equality, and no sweep runs
on a request path.

## Contribution rules

Route a change by its kind, not its path. A change that cannot be made in one
repository is a contract change and is filed as one change per repository. A
fork-reachable workflow uses `pull_request` only and carries no secret, no
`secrets: inherit`, no `pull_request_target`, no `workflow_run` and no write
token. See `CONTRIBUTING.md`.

## This package's deviations

Two deviations are recorded as package ADRs because the shared contract's
wording cannot hold for this repository:

- [ADR-0001](./docs/decisions/0001-analyzer-toolchain-is-a-dev-dependency.md) —
  the analyzer toolchain is a genuine third-party `require-dev` dependency,
  because this package supplies the toolchain it configures.
- [ADR-0002](./docs/decisions/0002-package-documents-stand-alone.md) — this
  package's documents link nothing into the private planning corpus.

The architecture rules behind `composer arch` are implemented: one PHPStan rule
per row in the quality spec, registered from `phpstan.neon`, each with a positive
and a negative fixture under `fixtures/architecture/` asserted by
`tests/Architecture/ArchitectureRulesTest.php`.

The POT generator (`composer i18n:check`) and the hook-reference generator
(`composer hooks:check`) are implemented against a shared mechanism: a
`GeneratedReference` renders deterministic text and a `ReferenceGate` compares
it with the committed file and reports the first differing line. The POT is
date-free so regeneration is byte-identical. Both scans are token-based and
never load the code they inspect. Fixtures under `fixtures/i18n/` and
`fixtures/hooks/` exercise the surface; the mutated copies under
`fixtures/i18n/mutated/` and `fixtures/hooks/mutated/` prove the gate fails.

`composer config:check` is the divergence gate. In a consumer it proves that
the four analyzer files reference the pinned `iniznet/mahout-devtools`
configuration, that `composer.lock` pins the package, that no local
architecture-rule copy exists, that the artifact set is present, and that the
repository's gate scripts are the family's. In this producer repository it
instead proves every shipped rule file is registered in `phpstan.neon`. The
fixture consumers under `fixtures/divergence/` and `fixtures/gates/` hold the
passing and failing cases.

The gate set lives in `resources/gates.json`: every canonical command with two
placeholders resolved from the repository under examination — `{domain}` from
its package name, `{source}` from the first path its `phpstan.neon` analyses.
A repository may add scripts of its own; it may not drop, edit or reorder a
canonical gate, and its `check` event may not run a step the manifest does not
declare. See ADR-0007.
