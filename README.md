# mahout-devtools

## What it is

The developer-time tool for the mahout family: it self-generates the WordPress
stubs every package type-checks against, ships the shared analyzer
configuration and test bootstrap, and provides the `doctor` installation check.

## Installation

There is no Packagist lane. Add the VCS repository and require the package as a
development dependency:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/iniznet/mahout-devtools.git" }
    ],
    "require-dev": {
        "iniznet/mahout-devtools": "^1.0"
    }
}
```

```bash
composer require --dev iniznet/mahout-devtools:^1.0
```

## The public `Contracts/` surface

| Interface | Role | Implementations |
|---|---|---|
| `Iniznet\Mahout\Devtools\Contracts\Check` | One named installation check, run by the doctor | the classes under `src/Doctor/Checks/` |
| `Iniznet\Mahout\Devtools\Contracts\Doctor` | Runs a check set and returns an ordered report | `src/Doctor/Doctor.php` |

The documented public concrete classes are `Doctor`,
`Doctor\DoctorReport`, `Doctor\CheckResult`, `Doctor\Status`,
`Stubs\StubGenerator`, `Console\Application`, `Console\Paths` and
`Console\ProcessRunner`. Everything under `src/Internal/` is off-limits to
consumers and may change in a patch release.

## A minimal usage example

```php
<?php

declare(strict_types=1);

use Iniznet\Mahout\Devtools\Doctor\Checks\PhpExtensionCheck;
use Iniznet\Mahout\Devtools\Doctor\Checks\PhpVersionCheck;
use Iniznet\Mahout\Devtools\Doctor\Doctor;

$report = (new Doctor([
    new PhpVersionCheck(),
    new PhpExtensionCheck(['json', 'hash', 'mysqli']),
]))->examine();

echo $report->toTable();
exit($report->exitCode());
```

## Compatibility

PHP 8.4 and later, WordPress 7.1 and later. Every interface under `Contracts/`
and every documented public concrete class is stable within a major version; the
`Internal/` namespace carries no guarantee and may change in a patch release. A
breaking change to a contract or a documented class requires a major version.

## Architecture

Every package in the family includes the analyzer configuration this repository
ships rather than copying it, so the rule set a repository enforces is a
consequence of its lockfile. The package has four responsibilities:

- **Stubs** — `bin/generate-stubs.php` boots the installed WordPress core and
  renders `stubs/wordpress-stubs.php` from reflection. Regeneration is
  byte-identical.
- **Analyzer configuration** — `phpstan.neon`, `psalm.xml`, `rector.php`
  and `.php-cs-fixer.dist.php` at the repository root. `phpstan.neon` is the
  pinned shared configuration; a consumer includes it and supplies its own
  paths. A relative `bootstrapFiles` entry resolves against the config file
  that declares it, so the stubs load from a consumer's tree unchanged.
- **Architecture rules** — `rules/` holds one PHPStan rule per banned construct
  in the family contract, registered from `phpstan.neon` and run by
  `composer arch`. Every rule carries a positive and a negative fixture under
  `fixtures/architecture/`, asserted in one PHPStan pass by
  `tests/Architecture/ArchitectureRulesTest.php`.
- **Test bootstrap** — `tests/bootstrap.php` and `tests/TestCase.php`, reused
  by every package's `phpunit.xml`.
- **POT generator** — `composer i18n:check` renders and verifies
  `languages/<slug>.pot` from the translation calls in the source. The output
  is date-free, so a second run is byte-identical; `composer i18n:generate`
  writes it. Source roots, text domain and output path are options.
- **Hook references** — `composer hooks:check` renders and verifies the two
  hook documents from every public constant in a `Hooks` class, written under
  `--outdir` as `actions.md` and `filters.md`; `composer hooks:generate` writes
  them. A constant whose docblock omits `@action` or `@filter` fails the
  generation rather than guessing.
- **Divergence** — `composer config:check` proves that this repository
  references the analyzer configuration and architecture rules shipped here
  rather than copying them, that the package is pinned in `composer.lock`,
  that the artifact set is present, and that its gate scripts match
  `resources/gates.json` — the family's gate set, so a dropped or redefined
  gate fails the build instead of surfacing in an audit. Run from the
  repository root.
- **`doctor`** — `bin/mahout-devtools doctor` runs the installation checks and
  exits non-zero on a failure. Besides the toolchain's own assembly it asserts the
  declared capacity prerequisites, measuring rather than reciting: the PHP files this
  installation can put on a request path against `opcache.max_accelerated_files`, the
  buffer pool against the site's own table statistics, the installed classmap in a
  child process, and `preload.php` as far as the platform allows. A prerequisite wrong
  in every mode fails; the production-only ones are judged against the installation's
  declared `WP_ENVIRONMENT_TYPE`, and one that declares nothing is told so; a
  development box gets a warning with its remedy; a check that cannot apply to this
  root is reported skipped.
- **`load:probe`** — `bin/mahout-devtools load:probe --url=… [--concurrency=8]
  [--requests=80]` issues a fixed concurrency through `curl_multi` and reports p50,
  p95, p99 and requests per second. Only a request that does not return a 2xx or 3xx
  sets an exit code: a slower machine is not a broken theme, and a smoke job that
  fails for the wrong reason gets deleted. Besides the toolchain's own assembly it asserts the
  declared capacity prerequisites, measuring rather than reciting: the PHP files
  this installation can put on a request path against
  `opcache.max_accelerated_files`, the buffer pool against the site's own table
  statistics, the installed classmap in a child process, and `preload.php` as far as
  the platform allows. A prerequisite wrong in every mode fails; one that is right on
  a development box and wrong in production warns with its remedy; one that cannot
  apply to this root is reported skipped.
- **`load:probe`** — `bin/mahout-devtools load:probe --url=… [--concurrency=8]
  [--requests=80]` issues a fixed concurrency through `curl_multi` and reports p50,
  p95, p99 and requests per second. Only a request that does not return a 2xx or 3xx
  sets an exit code: a slower machine is not a broken theme, and a smoke job that
  fails for the wrong reason gets deleted.

### Consuming the analyzer configuration

A consumer repository points its four root analyzer files at the pinned
package. `phpstan.neon` includes it; the other three require it. The shared
`rector.php` and `.php-cs-fixer.dist.php` resolve their paths from the
working directory, so requiring them analyses the consumer's `src`:

```php
// phpstan.neon
includes:
	- vendor/iniznet/mahout-devtools/phpstan.neon
parameters:
	paths:
		- src
```

```php
// rector.php
return require __DIR__.'/vendor/iniznet/mahout-devtools/rector.php';

// .php-cs-fixer.dist.php
return require __DIR__.'/vendor/iniznet/mahout-devtools/.php-cs-fixer.dist.php';
```

`psalm.xml` has no include mechanism, so it declares its own project files
but references `vendor/iniznet/mahout-devtools/stubs/wordpress-stubs.php` in
its `<stubs>` block. A local `rules/` directory, a local `rules:` block or a
local Psalm plugin fails `composer config:check`.

The package-scoped decisions are recorded under `docs/decisions/`. The
planning corpus that produced the family is private to the owner and is
deliberately not linked from this repository.

## Licence

GPL-2.0-or-later. See [LICENSE](./LICENSE).
