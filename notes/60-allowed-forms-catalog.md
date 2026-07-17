# D7 — allowed-forms catalog (draft, corpus-grounded)

Source of truth for "what the standard allows", per construct. Grounded in the
2026-07-17 harvest: all 17 ShipMonk OSS repos (fresh worktrees, all depending on
`shipmonk/coding-standard`), `src/` directories = standard-compliant gold.

## Scoreboard (src/ dirs, after harvest-driven fixes)

14 of 16 repos with src/: **100% byte-identical MATCH**. Total: 566 files,
561 MATCH, 5 REPAIR, 0 FATAL, 0 verifier failures. Every remaining repair is a
deliberate tightening (below).

## Principles (recap of notes/03, sharpened by the harvest)

1. Vertical arrangement (line breaks, rows, blank grouping) = author's choice.
2. Horizontal whitespace (spacing, **indentation**) = mandated, never a choice
   point. **Hard lesson (BinChain, fuzz-caught):** reading a choice off
   indentation breaks `format(perturb(x)) == format(x)` — choice points must be
   observable from LINE STRUCTURE only. Tried and reverted.
3. Structural triggers may force vertical form (count-based, never width).

## Per-construct catalog (as implemented; ✅ = corpus-validated)

### Collections — arrays, call args, closure `use` ✅
- FLAT: one line, `, ` separators, no space inside delimiters, NO trailing comma.
- BROKEN (any newline at own-level joint): rows at +1, one OR MORE elements per
  row (author's grouping), `, ` within a row, every row ends with `,` (trailing
  comma required), 0-1 blank lines between rows, closer on own line at opener's
  indent. Comments: own row, or trailing after a row / on the opener's line.
- Mandated: item indent exactly +1 (drift like +2 is repaired — dead-code-detector
  evidence: old standard never checked array indent).

### Parameter lists ✅
- Like collections but ONE param per row, and **≥2 params force BROKEN**
  (RequireMultiLineMethodSignature). Applies to named functions/methods; closures
  and arrow fns keep the author's choice.
- Param attributes: inline `#[A] int $x` OR own line above the param — author's
  choice (coverage-guard evidence).
- By-ref `&$x`, variadic `...$x` attached to the variable.

### Match ✅
- ALWAYS broken, one arm per line, trailing comma mandatory (also added before
  `}` — was verifier bug), `default` last by PHP semantics.
- Arm condition lists: author's ROW grouping preserved (passkeys CBOR tables,
  coverage-guard keyword tables), `,` at row ends; trailing comma before `=>`
  removed (layout comma).

### Binary/assignment chains, ternary ✅
- Per-joint: flat ` op ` or broken with LEADING operator at anchor+1.
- Trailing-operator breaks repaired to leading (phpstan-dev evidence).
- Continuation indent MANDATED at +1; aligned-with-first-operand occurs in the
  wild (static-reflection) but is repaired — see principle 2.
- Ternary broken form: `cond` stays, `? x` / `: y` at +1 (existing sniff shape).

### Conditions (if/while/switch/match subject) ✅
- FLAT, or fully-BROKEN (`(` newline, lines at +1, leading operators, `)` at
  construct indent). Half-broken headers (`if ($a\n && $b) {`) snap to the
  canonical broken form — phpstan-rules evidence: their own sniff intends this
  but misses these spots; we catch them. Deliberate tightening.

### Access chains ✅
- Per `->`/`?->` joint: flat or broken (leading operator at +1). `::`, `[...]`,
  `(...)`, `++/--` never break.

### Classes / members ✅
- Header one line; Allman brace; members at +1 with 0-1 blank preserved; blank
  before closing `}` 0-1 preserved; empty body `{\n}` or `{\n\n}` (the old
  standard's canonical). Method braces Allman; closure braces same-line.

### Statements ✅
- Own line at scope indent, 0-1 blank between (2+ clamped); trailing comments
  (incl. `@phpstan-ignore` directives) stay on their line — everywhere: after
  `;`, `{`, signature, attribute `]`, docblock, chain segment, inside broken
  conditions, on collection openers.

### File ✅
- `<?php` first; `declare(strict_types = 1);` same line allowed; single
  trailing newline.

## Open questions for the RFC / team

1. **Break after `=>`** (`'key' =>\n 'value'`): tolerated by old slevomat sniff,
   currently repaired to one line. 12 occurrences in foreign code, ~0 in ours.
   Proposal: keep repairing (not an allowed form).
2. **Aligned operator continuations** (static-reflection style): now repaired to
   +1 per principle 2. Team should bless the (small) reformat of that repo.
3. **Trailing commas in declarations on repos pinning standard <0.3**: repairs
   are correct per current standard; adoption = one-time diff.
4. `for` headers / grouping parens / `[index]`: deliberately flat-only (breaks
   repaired). Confirm or extend.
5. CRLF: currently rewritten to LF implicitly. Propose: explicit rule, LF only.

## Tightenings vs the phpcs implementation (features, not bugs)
- Array/continuation indent actually enforced (old ScopeIndent missed arrays).
- Half-broken conditions actually detected (old sniff missed).
- Ternary continuation +1 enforced (+2 drift existed).

## Fixed during harvest (engine bugs the corpus caught)
- by-ref param `&` swallowed by type parser (amp token-id discrimination).
- attribute `]` and docblock trailing comments dropped (trivia carriers).
- match arm condition rows joined; param attributes joined onto one line.
- anonymous-class recovery splitting `};`.
- 2 verifier false-fatals (match `}` comma, comma before `=>`).
