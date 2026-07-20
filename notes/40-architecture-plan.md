# Architecture plan v1 — permissive whitelist formatter for PHP

Builds on: `00` goal, `02` no-width, `03` core invariant, `04` whitelist templates,
`30` engine research. Locked: pure formatter · pure PHP/composer · no width rules ·
whitelist/default-deny · vertical layout free within allowed set, horizontal strict.

## 1. Concepts

- **Allowed-form set**: per construct, the set of acceptable layouts. Defined by a
  **template**: fixed token sequence + constraints on the **joints** (the whitespace gaps
  between adjacent significant tokens).
- **Joint constraint kinds**:
  - `none` — no whitespace allowed (e.g. before `,`, inside `(` when flat)
  - `space` — exactly one space (e.g. after `,` within a row, around binary operators)
  - `break(d)` — newline + indent to depth `d`
  - `break(d) | blank+break(d)` — newline, optionally preceded by exactly one blank line
  - `space | break(d)` — free choice point (this is where permissiveness lives)
- **Choice points**: a template's free joints (flat|broken group state, fill row
  boundaries, blank-line markers) + cross-joint constraints (trailing comma ⇔ broken,
  closer-on-own-line ⇔ broken, broken children at depth+1).
- **Engine outcomes per node** (default-deny):
  1. **MATCH** — some choice assignment reproduces the source byte-exactly → no-op.
  2. **REPAIR** — no assignment matches → print the assignment *closest* to source.
  3. **FATAL** — no template covers the construct/token placement → won't-fix error,
     file left byte-identical. A gap in coverage is a loud bug, never a silent allow.

## 2. Pipeline

```
source
  │ 1. lex          PhpToken::tokenize → significant tokens + GAP (whitespace string)
  │                 before each; comments are significant tokens (never silently moved)
  │ 2. layout-parse recursive descent over significant tokens → construct tree
  │                 (tokens = leaves). Unknown construct → FATAL. The parser IS the
  │                 totality check: every token must be consumed by some construct.
  │ 3. project+render
  │                 per node, derive the choice assignment FROM the observed gaps
  │                 (break state is directly observable: "is there a newline in this
  │                 joint"), then render the template under that assignment.
  │                 rendered == original slice → MATCH (byte-identical);
  │                 differs → REPAIR (the derivation makes it the closest assignment);
  │                 gap/token where template has no joint (usually a comment) → FATAL.
  │ 4. verify       re-tokenize output; token streams must be identical modulo
  │                 whitespace and explicitly allowed moves (trailing-comma add/remove
  │                 before a closer). Failure → internal FATAL, return input unchanged.
  ▼ output + report (changed spans, fatals)
```

### Why "project then render" instead of a search over assignments
Matching "does ANY assignment reproduce the source" sounds exponential, but every choice
point is *directly observable* from the source (newline present at that joint or not).
So projection is a single linear pass; rendering under the projected assignment and
comparing to the source IS the membership test. If the source deviates only in ways the
template mandates (wrong horizontal spacing, wrong indent, missing trailing comma), the
projected render *is* the nearest allowed form — repair for free. This reuses the
dprint/ruff/mago "read the break state off the source" trick (`20` §3) as a decision
procedure rather than a heuristic.

### Closest-repair definition (v1)
Preserve the author's break choice at every joint where breaking is allowed; normalize
everything mandated (horizontal whitespace, indent, trailing comma, clamp 2+ blank lines
to 1, drop breaks at joints where breaking is not allowed). This is "closest" in the
sense of: identical choice-point assignment wherever the author's choice was legal.

## 3. Idempotency & convergence
Structural, not empirical: repair output is a template assignment; projecting it again
yields the same assignment → fixed point after one pass. No fixer-priority ordering, no
convergence loop (php-cs-fixer's fragility, `16`, designed out). Tests still double-run.

## 4. Comment policy (the main fatal source)
Comments are significant tokens; templates declare where they may sit:
- own line at statement level (verbatim)
- own row inside a broken collection (verbatim; forces the collection broken)
- everything else (inline `/* */` between tokens, trailing after an element, …) → FATAL
  in v1; individual placements get legalized deliberately, one template at a time.
Fatals never break the file: the construct (whole file in PoC) is left byte-identical
and reported.

## 5. Safety
- Verification gate (stage 4) guarantees output ≡ input modulo whitespace + allowed
  comma moves — semantics can never change (CSharpier's idea, `15`).
- Any FATAL → input returned unchanged. The formatter is total and safe by construction.

## 6. Config surface (post-PoC)
The template set IS the standard. Options only toggle documented choice points
(e.g. "is `space|break` allowed at argument joints?"), never invent layouts. PoC
hardcodes the shipmonk standard: 4-space indent, trailing comma iff broken, one space
after comma / around binary ops / around `=>`, closer on own line at opener's depth,
≤1 blank line.

## 7. Testing
- **Golden fixtures** (kept from old repo, `01`): `X.php` → expected `X.php.fixed`;
  valid inputs have output == input (the permissive property, asserted byte-exact).
- **Idempotency**: format(format(x)) == format(x) on every fixture.
- **Fatality fixtures**: expected error message.
- **Totality/corpus fuzz** (post-PoC): run over a real codebase; every file must
  MATCH/REPAIR/FATAL; measure fatal rate (target ≈ 0) and diff plausibility.
- Verification gate doubles as a runtime oracle in all tests.

## 8. Performance
Single linear pass, no backtracking, no width search, string concatenation only.
Trivially parallel per file. Expected far faster than phpcs.

## 9. PoC scope (this iteration)
Grammar subset: `<?php` header (+ optional same-line `declare(strict_types = 1);`),
expression statements, binary/unary expressions over atoms (numbers, strings, variables,
names), array literals (incl. `=>` pairs, nesting), function calls, comments per §4.
Everything else → FATAL (by design — proves default-deny).

**Success criteria**
1. The four user array forms (`03`) pass through byte-identical.
2. Horizontal deviations repaired without touching row structure or blank grouping.
3. Trailing comma enforced iff broken; indent snapped to depth; blanks clamped to 1.
4. Unknown constructs / illegal comment placements → clean FATAL, file unchanged.
5. Idempotent on all fixtures; verification gate green.

**PoC simplification (explicit):** templates are implemented as per-node
`render()` methods (projection fused with rendering) rather than a declarative Template
IR data structure. Behaviorally identical (whitelist semantics, totality via the parser);
formalizing the IR as data is a post-PoC refactor once the model is validated.

## 10. Roadmap after PoC
1. Extend the layout grammar toward full PHP (statements/blocks → class members →
   attributes/heredoc/inline-HTML last; fatal rate on the shipmonk monorepo is the
   progress metric).
2. Formalize the Template IR as data (enables config, docs generation, exhaustive
   choice-point listing = D6).
3. D7: per-construct allowed-form catalog (arrays done in `03`).
4. Package as `shipmonk/coding-standard` v2: composer bin, CI reporter, baseline story.
