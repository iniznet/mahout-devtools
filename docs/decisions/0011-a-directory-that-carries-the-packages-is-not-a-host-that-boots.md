# ADR-0011 — A directory that carries the packages is not a host that boots

Status: accepted

## Context

`CompositionRootCheck` (ADR-0010's companion, shipped in the same change as the kernel's
process claim) counted host trees: every `wp-content/{plugins,mu-plugins,themes}/*/vendor/iniznet/mahout-*`
was a host, and more than one was a failure. That was right about the collision and wrong
about the world.

It became wrong the moment the second starter existed. `wp-content/themes/howdah` and
`wp-content/plugins/kumki` sit side by side in a development checkout, both with their
vendor trees, one active and one not — and both repositories' `composer check` failed with
a message telling the operator that their workstation was misconfigured. A gate whose
first act is to cry wolf about an ordinary state is a gate that gets widened, ignored, or
deleted within a week, and the collision it exists to prevent is silent corruption in a
production installation. The cost of a false failure is not the annoyance; it is the
true failure that follows it.

The underlying fact: a directory carrying `vendor/iniznet` is a *source tree*. Whether
WordPress will load it is a different question with a different answer, recorded in the
site's own options: `stylesheet` names the active theme's directory, `active_plugins`
names the activated plugins as `dir/file.php`. `mu-plugins` has no option, because
everything there is always loaded.

## Decision

Separate the two questions and compare their answers.

- the filesystem scan stays as it was: which trees carry the packages;
- a new `ActiveHosts` reads which of them WordPress would load, from the site's options
table, over the same connection and with the same text-parsing of `wp-config.php` that
`InnoDBBufferPoolCheck` already uses;
- the verdict is about loaded hosts, not directories:

| trees | loaded | result |
|---|---|---|
| 0 | — | skip: nothing installs the packages |
| 1 | not asked | **pass** — a lone tree cannot collide, so the database is not consulted at all |
| n | 1 | **pass**, naming the root of record and every source-only tree |
| n | ≥ 2 | **fail**, naming every bootable host |
| n | 0 | **warn**: nothing collides today, and the decision is pending |
| n | unreadable | **warn**, naming the trees and what could not be read |

`DatabaseCredentials` grew a `tablePrefix` read, and an absent prefix is reported as
absent rather than defaulted to core's own `wp_`. A check that assumed the default would
read a table that may not be the site's and report success about a database it never
looked at — the precise failure mode this whole area exists to avoid.

A `mu-plugins` tree counts as loaded without being asked. There is no option to consult
and no way for it not to load, so the conservative reading is also the accurate one.

## Consequences

The check now reads the site's database, which it did not before, and only when there is
more than one tree to adjudicate: the single-host case — which is every installation that
has never heard of this decision — pays no query and needs no credentials. `doctor` has
always required `wp-config.php` to be readable for the buffer-pool check, so this adds a
question rather than a new dependency.

The second gate this plan originally promised, a resolution-parity check comparing the
pinned package references across host trees, is **not** built, and this is the reason:
with bootability established, skew can only bite when two trees are both loadable, and
that state already fails above. A parity check would be a mechanism for a hazard the
primary check already closes, and the contract refuses paths kept in case.

The development state this decision exists for is now legible in the report rather than
flagged as a fault:

```
PASS  Composition root  wp-content/themes/howdah is the root of record;
                        1 source-only tree(s) are not loaded: wp-content/plugins/kumki
```

Two starters can be developed on one workstation, and one of them can be activated. What
remains refused is the thing that was always refused: two hosts booting the same
namespace of state.
