# ADR-0010 — A rule names a boundary by position, not by one host's class

Status: accepted

## Context

Four architecture rules carried `Iniznet\Howdah\…` as a literal:
`SuperglobalsOnlyInRequestRule` compared the analysing class against the theme's
`Support\Request` by exact name, `NoStaticServiceAccessRule` exempted three of the
theme's classes, and `InternalNotCrossPackageRule` recognised `Iniznet\Howdah\` as a
package while every other namespace fell through as `null`.

The consequence of a literal is not a false alarm, it is a quiet withdrawal: the rule
stops applying wherever the second installation lives. `packageOf()` returning null
makes `InternalNotCrossPackageRule` return no violation, so a plugin reaching into the
texture of another host's `Internal\` would be blessed by a gate that appears in the
report as green. `SuperglobalsOnlyInRequestRule` fails in the opposite direction — a
second host's own request adapter, correctly built at the correct position, is
reported as reading a superglobal illegally. One rule is too strict for the new host
and the other too lenient, and both wrongnesses are invisible from inside the theme,
where every fixture happens to be shaped like the theme.

## Decision

A boundary is named by its position in a host, which is the form the contract already
describes it in.

- The request adapter is `<Host>\Support\Request`.
- A host package is the vendor segment after `Iniznet\`: `Iniznet\Mahout\Db\…` is `Db`,
  `Iniznet\Howdah\…` is `Howdah`, `Iniznet\Kumki\…` is `Kumki`, with no host named.
- The composition-root exemption list is deleted rather than generalised. Those classes
  were never in the rule's reach: it flags classes that resolve collaborators, and
  `Bootstrap`, `Support\Request` and `Render\Surfaces` do not match its own test. A list
  naming them implied the privilege belonged to one installation instead of to the
  shape of the boundary.

`BoundedHowdahStatementRule` keeps its `(?:howdah|mahout)_` table pattern and is not
relaxed here. It matches a *table name*, not a class, and the rule is about the tables
the packages own. A host that declared its own custom table would be outside it — which
is a reason for a host not to declare one, not a reason to widen a pattern until it
matches everything.

## Consequences

Five fixtures say what the generalisation means, in both directions: a second host's
`Support\Request` reads `$_GET` and is clean; a second host's admin class reads `$_GET`
and fires; a host reaching into its own `Internal\` is clean; a host reaching into
*another* host's `Internal\` fires. A generalisation tested only on the side that
passes is a widening, and the two negative cases are what make it a rule.

A starter built by `mahout-scaffold --host=plugin` is gated by the same rules as the
theme starter, which is the property this decision exists to buy: adding a host shape
does not add an ungated codebase.
