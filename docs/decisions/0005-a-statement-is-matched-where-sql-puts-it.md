# 0005 — A statement is matched where SQL puts it, not where the letters fall

Status: accepted.

## Context

Two architecture rules answer a question about *statements*, and both answered it by searching a
string literal for a bare word:

1. `BoundedHowdahStatementRule` tested `/\bhowdah\w*\b/i`. A table this project owns is declared
   as `{$wpdb->prefix}howdah_<entity>` (a theme-owned custom table) or `{$wpdb->prefix}mahout_<name>`
   (a package table: `mahout_migrations`, `mahout_field_values`, `mahout_field_items`). At runtime the
   name is therefore `wp_howdah_values` or `wptests_mahout_migrations`. The underscore a prefix ends
   with is a word character, so there is no `\b` before `howdah` in `wptests_howdah_values`, and the
   pattern never saw a package table at all. The rule was silent on every real table name. It also read
   only `String_` nodes, so `"SELECT * FROM {$wpdb->prefix}howdah_values"` — the way the prefix is
   written in PHP — was never analysed.
2. `TransactionOnlyInGatewayRule` tested `/\b(START\s+TRANSACTION|COM|MIT|ROL|BACK)\b/` over any
   literal. It fired on the word inside an exception message, so it constrained prose. A message was
   reworded to read "reversal" and "cannot be reversed" to keep the gate green — the gate was shaping
   the code's vocabulary instead of its statements.

A rule whose only fixture uses the convenient form proves nothing: a bare `howdah_series` and a bare
`'START TRANSACTION'` both matched the old patterns, which is exactly why the defects survived
review.

## Decision

A rule about a statement matches the statement, and its fixture states the real-world case.

- The bounded-statement rule reads the statement expression, flattening a scalar literal, an
  interpolated string and a concatenation into one text. A part it cannot read — an interpolated
  expression or a concatenated variable — becomes a marker rather than disappearing, so a name that
  exists only in a variable can never be mistaken for a name the rule has seen.
- It matches a project table in a **table position**: after `FROM`, `JOIN`, `INTO`, `UPDATE` or
  `TABLE`, through identifier characters only. The name boundary is a lookbehind for something that is
  not part of the name, because `\b` cannot express a boundary after an underscore.
- The transaction rule matches the keyword in a **statement position**: the start of the literal or
  the character after a semicolon, optionally after whitespace. Prose that mentions a rollback is
  prose; `$wpdb->query('COMMIT')` is a statement.
- `TransactionOnlyInGatewayRule::STATEMENT_PATTERN` is the public, single definition of "is this
  literal a transaction statement". A consumer that scans its own source for one reads the constant
  instead of repeating the pattern, so the gate and its proof cannot drift apart.

## Rejected alternatives

| Alternative | Why not |
|---|---|
| Keep `/\bhowdah\w*\b/` and add `wptests_` | Guesses one site's prefix. The prefix is a configured value; the pattern must not name it. |
| Match `howdah` anywhere in the literal again, with a corrected boundary | Fires on an option name or an index name that merely contains the word — for example `WHERE option_name = 'mahout_db_schema_version'`. A rule about a table must know it is looking at a table. |
| Scan only scalar literals and declare interpolation out of scope | The interpolated form is the canonical one, so the gate would stay decorative. |
| Anchor the transaction rule to the whole literal being exactly `COMMIT` | Rejects a leading newline, surrounding whitespace or a trailing semicolon, none of which move the keyword out of statement position. |
| Weaken the rule so a convenient fixture passes | This is the defect. The fixture moves to the real case instead. |

## Consequences

- Each fixed rule owns an isolated violation fixture per claim —
  `BoundedHowdahStatementPrefixed.php`, `BoundedHowdahStatementInterpolated.php`,
  `BoundedHowdahStatementPackageTable.php`, `TransactionStatementWithoutGateway.php`,
  `TransactionRollbackStatement.php` — because a file that carries two forms passes even if only one of
  them fires.
- The clean transaction fixture carries the restored message text, so "prose does not fire" is proved
  against the message a package actually throws rather than against a sentence chosen to avoid the
  pattern.
- A statement whose table name lives in a variable is invisible to a static rule. That is a stated
  limit, not a silent pass: the package that owns such a statement owes a runtime bounded-statement
  test, and the corpus already requires one.
