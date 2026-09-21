# 0006 — A capability is declared on a Capabilities type, and a literal is never the check

Status: accepted.

## Context

`CapabilityLiteralOnlyInCapabilitiesRule` forbids a string literal as the first argument of
`current_user_can()`. It exempted any code inside a class whose short name was `Capabilities`, on the
reading that the type is where the literal is allowed to live.

That exemption is a loophole and the fixtures proved the wrong thing. A class merely *called*
`Capabilities` is not a declaration of anything: it can gate a screen with
`current_user_can('edit_theme_options')` and pass the gate. The clean fixture did exactly that, so the
rule's own proof demonstrated the construct the rule exists to prevent.

The question a package faced was whether it must invent a `Capabilities` type for a single check. The
corpus answers it in two places, and they agree: capability names are typed constants on a
`Capabilities` type, exactly as hook names are constants on a `Hooks` class; and the gate for
authorisation is stated as "no capability is compared by role, and no capability is a literal at the
check site". A capability check is always through a declared name, so the type is required wherever a
capability is checked. There is no package small enough to be exempt.

## Decision

A string literal at a `current_user_can()` call site is always a violation. The class-name exemption
is removed. The literal belongs on the `Capabilities` type as the value of a typed constant or an
enum case, and the check site names that constant.

## Rejected alternatives

| Alternative | Why not |
|---|---|
| Exempt a package that has no `Capabilities` type | It would make the rule depend on the shape of the tree it analyses, so the same call site would be legal in one package and illegal in another. The corpus's rule has no package-shaped qualifier. |
| Exempt a class named `Capabilities` (the status quo) | It accepts a literal at a check site, which is the banned construct. A type is a declaration of names, not a licence to inline them. |
| Leave the rule and rewrite only the fixture | The rule would still accept the loophole in production code; the fixture would merely stop advertising it. |

## Consequences

- `fixtures/architecture/clean/CapabilityLiteralOnlyInCapabilitiesRule.php` is now the corpus's shape:
  a `Capabilities` enum carrying the name, and a caller gating on `Capabilities::EditThemeOptions->value`.
- `fixtures/architecture/violations/CapabilityLiteralInsideCapabilitiesClass.php` is the proof the
  loophole is closed: a class named `Capabilities` that gates on a literal now fails.
- `Violation::shortName()` is still used by the nonce rule; only this rule stopped needing it.
