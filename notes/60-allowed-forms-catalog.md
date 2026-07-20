# D7 — allowed-forms catalog (draft, corpus-grounded)

Source of truth for "what the standard allows", per construct. Grounded in the
2026-07-17 harvest: all 17 ShipMonk OSS repos (fresh worktrees, all depending on
`shipmonk/coding-standard`), `src/` directories = standard-compliant gold.

## Scoreboard (src/ dirs, after harvest-driven fixes)

14 of 16 repos with src/: **100% byte-identical MATCH**. Total: 566 files,
561 MATCH, 5 REPAIR, 0 FATAL, 0 verifier failures. Every remaining repair is a
deliberate tightening (below).

### Monorepo `backend/src` (2026-07-17, 18 096 files — the scale test)

`files=18096 match=17606 repair=489 recovered=1 fatal=0 verify-failures=0
safety-failures=0`. **97.3% byte-identical MATCH, 0 fatals, 0 verifier failures.**
The single recovery is a genuine won't-fix (a multi-line `A | B | C` union type
with a `// note` on each alternative — no position in the flat DNF form `A|B`).
The 489 repairs are the deliberate tightenings below (half-broken conditions and
ternaries, indent drift, blank-line clamps, pre-0.3 trailing commas). This run
drove the comment-handling work: docblock/comment rows inside collections,
comment rows between match-arm conditions, leading/trailing comment rows in
conditions, `return //…\n …`, comment before a class `{`, and — the biggest win —
**multi-line `catch` type lists** (519 trailing-`|` lines were being flattened;
now preserved, turning ~500 repairs into matches).

## Principles (recap of notes/03, sharpened by the harvest)

1. Vertical arrangement (line breaks, rows, blank grouping) = author's choice —
   EXCEPT where a structural rule mandates it (principle 3): match is always
   broken, and class-member blank spacing is enforced (see "Member spacing").
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
- Comment rows may be single-line OR multi-line (docblock `/** @var … */` on a
  promoted ctor property, block `/* … */`); `*`-prefixed continuation lines are
  re-indented to the row depth, other continuation lines kept verbatim.

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
- Arm condition lists are a full comma-separated collection (rows, blank-line
  grouping, own-line comment rows between conditions, trailing `//` notes on a
  condition riding its comma) — the sole difference from a bracketed collection
  is NO mandatory trailing comma before `=>` (that layout comma is dropped).
  Modelled via `MatchCondItem` (a `ListItem`) so the collection machinery is
  reused. Evidence: enum→fee-type maps documenting each case (backend/src).

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
- Own-line comment rows right after `(` or right before `)` are preserved at +1
  (and force the broken form) — the "explain the guard / trailing rationale"
  pattern (backend/src). Comments before a binary operator or `->` joint likewise
  ride onto their own continuation line.

### Try / catch ✅
- `} catch (` on one line. Type list FLAT (`A | B $e`, ` | ` WITH spaces —
  CatchSpacing) or BROKEN: `(` on the catch line, each type on its own line at +1
  with a TRAILING ` |`, the variable after the last type, `)` on its own line at
  the catch indent. Trailing `|` is deliberate — 519 unanimous corpus lines
  (unlike boolean chains, which lead). A comment riding on `(` (a line-targeted
  `@phpstan-ignore` on the catch) also forces the broken form.

### Access chains ✅
- Per `->`/`?->` joint: flat or broken (leading operator at +1). `::`, `[...]`,
  `(...)`, `++/--` never break.

### Classes / members ✅
- Header one line; Allman brace; members at +1. Method braces Allman; closure
  braces same-line.
- **Blank-line spacing is MANDATED here** (the one construct where vertical space
  is enforced, not the author's — see "Member spacing" below). Empty body keeps
  the author's `{\n}` or `{\n\n}`.
- Own-line comment rows between the header and `{` are preserved at the class
  indent (the `// phpcs:enable …` pragma pattern, backend/src).

### Member spacing (MANDATED blanks) ✅
The sole place blank lines are enforced rather than clamped-to-author (`MemberSpacing`,
corpus-grounded on 18 096 backend/src files):
- **blank after `{`** (before the first member) — 18 073 classes have it, 0 without;
- **blank before `}`** (after the last member, non-empty body) — 18 093 vs 28;
- **a blank surrounds every method** — mandatory at a member boundary when either
  side is a method (function-like), INCLUDING the blank before a method's leading
  docblock/attribute block (which the tokenizer emits as separate members — the
  blank goes before the comment block, never between it and the method);
- **fields stay the author's choice** — consecutive properties / constants / enum
  cases may sit with or without a blank (813 adjacent consts, 974 adjacent cases
  with none). Applies to class/interface/trait/enum and anonymous classes; blocks,
  switch bodies and file-level series keep the author's blanks (0-1 clamp).
- Cost on backend/src: +46 repairs (files missing a mandated blank), 0 on the OSS
  gold corpora — the convention is effectively universal already.

### Statements ✅
- Own line at scope indent, 0-1 blank between (2+ clamped); trailing comments
  (incl. `@phpstan-ignore` directives) stay on their line — everywhere: after
  `;`, `{`, signature, attribute `]`, docblock, chain segment, inside broken
  conditions, on collection openers.
- `return`/`throw`/`echo`/… with a trailing comment on the keyword, or own-line
  comment rows before the expression, push the whole expression to +1 with the
  comment rows preserved between (`return //why\n //ctx\n $a\n || $b;`).

### Types (won't-fix) ✅
- Union/intersection types render FLAT DNF, no spaces (`A|B`, 220 corpus uses).
  A comment INSIDE a type (`A | //note\n B` multi-line union) has no position in
  that form — the statement recovers verbatim + a violation (the ONE recovery in
  18 096 backend files). A trailing comment on the LAST type token is fine (the
  caller's Allman brace / `;` provides the break): `: float //@phpstan-ignore`.

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

## Fixed during the backend/src scale run (2026-07-17)
- multi-line `catch` type lists were flattened → now a first-class broken form
  (trailing `|`); ~500 repairs became matches.
- verifier dropped a layout comma's OWN trailing comment when deleting the comma
  (`[] //note` vs repaired `[], //note` diverged) → comment re-inserted on both.
- match-arm condition comma trivia was synthesized, losing source `//` notes on a
  condition → source commas re-emitted (via `MatchCondItem`).
- comment rows now supported: inside collections (docblocks), between match
  conditions, at condition `(`/`)` boundaries, after `return`, before a class `{`.
- `ParenExpr` gained the broken form (was flat-only) so grouped sub-conditions
  with breaks/comments stop being flattened.
- `Emitter::layoutComma()` writes a synthesized comma BEFORE a pending trailing
  comment (the one text emission that bypasses the trailing-trivia guard).
