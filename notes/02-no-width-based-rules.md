# DECISION: avoid all / almost all width-based rules (user, 2026-07-17)

This is the most architecture-shaping decision so far. Read alongside `20` and `21`.

## What it means
No (or almost no) rule keys off the printed **line width** / column count. We do NOT have a
"max line length" that triggers wrapping. We never decide flat-vs-expanded because a line
is "too long".

## Why it's central to the permissive vision
A width-based formatter (Prettier, oxc, Ruff, Biome, dprint, csharpier, gofmt…) MUST choose
flat-vs-expanded for the user: the column threshold forces exactly one form. A permissive
formatter says "you chose flat or expanded; both are allowed; I preserve your choice."
**Removing width IS what makes multiple allowed forms possible** — they are the same idea.
`$a = [1, 2]` vs the multi-line array are both legal precisely because no width rule adjudicates.

## Consequence for the researched engines (big)
The **core engine** of every formatter we studied — the Wadler/Prettier "Doc" algebra
(`group`/`indent`/soft·hard·`line`/`if_break`/`fill`) + the two-phase printer whose whole
job is measuring `fits` against `line_width` — exists to make width-driven break decisions.
**Without width rules we do not need it.** This confirms the user's hypothesis that maybe
none of oxc/ruff/dprint's core is applicable. It largely isn't.

### What DOES survive from the research (the non-width parts)
- **Lossless data model**: tree + trivia (CST, or AST+trivia+source) to reproduce untouched
  code byte-exact and reason structurally. (biome/mago/ruff)
- **Comment handling**: leading/dangling/trailing classification, print-once invariant. (ruff/biome)
- **Source-signal detection as TRIGGERS, not width**: "newline after opener" / "trailing
  comma present" → decide/preserve a form. (dprint/ruff/mago) — but used as structural
  triggers, never as width fallback.
- **Re-parse + tree-diff verification gate**. (csharpier)
- **`fmt:off`/ignore + verbatim passthrough**, golden-file fixtures.

### What we DROP
- The `fits`/width-measurement printer, `best_fitting`, width-driven `group` breaking.
- The idea of a single canonical reprint. We do not reprint by default; we preserve.

## Revised architecture (supersedes `20` §3 option C lean)
Shift from a pretty-printer toward a **tree/token localized-edit model** — a modernized,
structured phpcs:
1. Parse to a **lossless tree** (structure + trivia + recoverable source).
2. Run **rules** that inspect the tree. Each rule either:
   - normalizes **local whitespace** within whatever form the user chose (spaces around
     operators, after commas, indentation depth, blank-line counts), OR
   - checks a **structural / count-based** property and, only if violated, rewrites that
     construct's layout via a simple mechanical transform (e.g. "≥2 params ⇒ multiline":
     put each param on its own line at indent+1; "single-line ⇒ no trailing comma").
   None of these measure columns.
3. **Splice** only the touched spans back into the original text (localized edits, à la
   phpcs `Fixer`, or nikic/PHP-Parser's format-preserving printer which reprints only
   changed nodes). Everything untouched stays byte-identical → zero churn.
4. **Verify** by re-parsing output and structurally diffing against input (semantics
   unchanged; only whitespace/allowed-token-moves differ).

## Rules that FORCE a form change are fine — they're just not width-based
The incumbent already has non-width triggers we keep:
- `RequireMultiLineMethodSignature minParametersCount=2` — **count-based**, not width.
- `RequireTrailingCommaInCall` + `DisallowTrailingCommaInCall onlySingleLine=true` —
  layout-consistency: multiline ⇒ trailing comma; single-line ⇒ none.
- `SingleLineArrayWhitespace`, `MultiLineArrayEndBracketPlacement` — normalize whichever
  form is used.
When such a rule forces expansion, generating the expanded layout is a mechanical
per-construct transform (elements → own lines at indent+1), NOT a width-fitting search.

## Open sub-question
"almost all" — are there ANY width-adjacent rules we keep? e.g. do we still want an
*advisory* max-line-length **report** (not an autofix)? Probably out of scope for a pure
formatter; PHPStan/editor can warn. Confirm, but default = no width anywhere.
