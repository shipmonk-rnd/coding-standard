# Synthesis — formatter architecture research & recommendation

Cross-reading of six modern formatters (oxc `10`, ruff `11`, dprint `12`, biome `13`,
mago `14`, csharpier `15`) plus the existing standard (`01`). Answers the two open
questions from `00`: **AST vs raw tokens?** and **what IR?** — and gives a recommendation.

---

## 1. The landscape on two axes

| Formatter | Language | Data model | Layout IR | Preserves user layout? |
|-----------|----------|-----------|-----------|------------------------|
| oxc       | JS/TS    | plain AST + comments side-array + rescanned source | Wadler "Doc" (ported from Biome) | canonical, 3 source-keyed exceptions (objects/blank-lines/verbatim) |
| ruff      | Python   | AST + token/trivia ranges + source | Wadler "Doc" (forked from Biome) | canonical + **magic trailing comma** + blank lines + `fmt:off` |
| biome     | JS/TS/…  | **lossless CST** (red-green, trivia on tokens) | Wadler "Doc" (the origin of ruff/oxc's) | canonical + objects-expand + blank lines; range-format splicing |
| dprint    | TS/…     | AST + comment tracker | **conditional/backtracking** print-items (not Wadler) | **preserve-if-user-broke** by default (newline after opener) |
| mago      | **PHP**  | AST + separate `trivia` buffer + source | Wadler "Doc" (Prettier port) | **`BreakMode::Preserve` per construct; arrays default true** |
| csharpier | C#       | Roslyn **lossless** tree | Wadler "Doc" (Prettier port) | fully canonical; **re-parse+tree-diff verification gate** |

Two clear industry consensuses:
- **IR: the Wadler/Prettier "Doc" algebra won.** Five of six use `group`/`indent`/
  `line`(soft/hard)/`if_break`/`fill`/`best_fitting`, with a two-phase printer:
  `propagate_expand` (force-open any group containing a hard break) then a width-measured
  `fits` check per group against a target line width. dprint is the lone dissenter with a
  more general *conditional/backtracking* IR (conditions are closures over live writer
  state, can look ahead) — more powerful, harder to reason about, slower. **Default choice
  is the Doc algebra; dprint's model is the fallback if we hit a layout the Doc can't
  express.**
- **Data model: nobody uses a raw token stream, and nobody uses a bare AST either.** They
  all use *a structural tree PLUS a way to recover the exact original bytes.* Two flavors:
  - **Lossless CST** (biome, csharpier/Roslyn): trivia (whitespace+comments) attached to
    tokens; every byte reproducible from the tree itself.
  - **AST + side trivia + original `source_text`** (oxc, ruff, mago): the tree is lossy,
    but comments live in a side buffer and lexical facts ("was there a newline here?") are
    recovered by re-scanning `source_text` over node spans.

  Both give the same two superpowers we need: (a) reason **structurally** (per-construct
  rules), and (b) **reproduce untouched regions byte-exact**. The CST makes (b) free and
  makes partial re-splicing natural (biome's `format_range`); the AST+source approach is
  lighter to build and is what the PHP-relevant tool (mago) already does.

## 2. Answering "AST vs raw tokens?"

**Neither extreme. Use a syntax tree that can always reproduce the original source.**

- **Raw tokens (today's phpcs)**: great at "only touch what's wrong" (localized edits, no
  reprint — see `01`), but has no structure, so width-sensitive/whole-construct decisions
  ("should this call wrap?") and cross-token rules are painful and bug-prone. This is a
  big part of why the incumbent is unpleasant to extend.
- **Bare AST (lossy)**: great structure, but you've thrown away the formatting you're
  supposed to be judging. Forces a full canonical reprint — fights our core principle.
- **Winner: tree + fidelity.** Either a lossless CST or AST+trivia+source. This is the
  unanimous choice of every modern formatter and is the right foundation here.

## 3. The core mechanism for "multiple allowed forms" (our differentiator)

Every formatter that preserves user layout uses the **same three-step recipe**:
1. **Detect a signal in the original source** — cheap, local. Two signals dominate:
   - *newline after the opening delimiter* (dprint `node_helpers.rs`, oxc/biome objects,
     mago arrays) — "user wrote it expanded".
   - *magic trailing comma* (ruff, Black, Prettier) — "user pinned it expanded".
2. **Encode BOTH layouts once** via conditional IR: `if_group_breaks(",")` so the trailing
   comma only appears when expanded; the element separators are soft lines.
3. **Pin the chosen layout** with a one-way force: ruff's `expand_parent()` →
   `propagate_expand`; mago's `BreakMode::Preserve`; dprint flipping a shared
   `is_multi_line` condition.

**mago has already generalized this to PHP**: a `preserve_breaking_*` setting per
construct (arrays default `true`, args/params/chains opt-in). Our vision = **take this to
its logical end: preserve-by-default for (almost) every construct**, so the formatter's
fixed point is a *set* of allowed forms, and canonicalization happens only when the input
matches none of them. `best_fitting` (biome/oxc/ruff) already models "N acceptable
renderings of one node" natively — the IR primitive for multi-form exists.

### The subtlety that decides the whole architecture
A Prettier-style formatter **always reprints the entire file** — it just happens to
reproduce the input when the input was already allowed. That is fine *only if every
construct has a faithful preserve/allow rule*; any construct we haven't taught will get
silently canonicalized → churn on code unrelated to the actual violation. Our principle
("only reformat when the used formatting is not allowed") is stricter than "preserve where
we remembered to". Two ways to honor it:

- **(A) Preserve-first pretty-printer.** Full Doc reprint, but every construct defaults to
  a preserve/verbatim rule; a construct is only re-laid-out when it violates an explicit
  rule. (mago's model with all `preserve_*` flags on, plus a verbatim fallback.) Clean IR,
  width-aware wrapping for free; risk is "accidental canonicalization" of anything without
  an explicit allow-rule — must default unknown constructs to verbatim.
- **(B) Lint-and-fix, localized edits.** Like phpcs today: leave the byte stream alone,
  detect violating spans, splice minimal fixes. Zero churn by construction; but you lose
  the Doc engine's width-based wrapping and must hand-roll each rule (and re-derive
  structure the token stream lacks).
- **(C) Hybrid (recommended).** Tree + Doc engine, driven in preserve-first mode (A), but
  scoped like biome's `format_range`: identify only the subtrees that violate a rule,
  re-emit *those* through the Doc, and splice them back into the original text; everything
  else is emitted verbatim from source. Plus csharpier's **re-parse + tree-diff
  verification** as a safety gate so a splice can never change semantics. This gives
  width-aware reformatting where we need it, byte-exact preservation everywhere else, and
  minimal churn — the best fit for the stated principle.

## 4. Reusable ideas worth stealing (regardless of language)
- Wadler Doc IR + two-phase (`propagate_expand` → width-measured `fits`) printer — the
  proven core. (all)
- `best_fitting` / `ConditionalGroup` = first-class "several valid renderings". (biome/oxc/ruff/csharpier)
- Source-signal → conditional-IR → one-way-expand recipe for preserving user choice. (ruff/dprint/mago)
- Per-construct `preserve_breaking_*` config, defaulted permissive. (mago)
- Comment classification leading/dangling/trailing + "every comment printed exactly once"
  invariant. (ruff/biome) — comments are the hardest part of any formatter.
- Range/partial formatting via immutable-tree splicing + source map. (biome)
- `fmt: off` / `prettier-ignore` / verbatim passthrough. (ruff/biome/csharpier)
- Re-parse-and-structurally-diff verification gate. (csharpier) — invaluable when splicing.
- Golden-file `input.php` / `input.php.fixed` fixtures. (existing standard `01`)

## 5. Recommendation

**Architecture:** tree-with-fidelity (AST + trivia + source, *or* lossless CST) + Wadler
Doc IR + **preserve-first, violation-scoped** printing (§3 option C) + re-parse
verification gate. This is the synthesis of mago (preserve defaults) + biome (CST +
range splice) + csharpier (verification) tuned to the "set of allowed forms" principle.

**The pivotal build-vs-reuse decision — mago.** mago is a mature, permissively-licensed
(MIT/Apache-2.0) PHP parser **and** Prettier-style formatter in Rust that *already*
implements per-construct break-preservation. It is ~70-80% of the engine we'd otherwise
build. Realistic options (needs user decision — see `21`):
  1. **Fork/build on mago (Rust).** Fastest to a working, fast formatter; flip
     `preserve_*` defaults to permissive, add violation-scoping + verification, reimplement
     our rule set on top. Cost: Rust expertise; distribution is a compiled binary (mago
     already ships binaries + a composer installer bridge, so this is solved-ish).
  2. **Build in PHP (native to the ecosystem).** Composer-installable, no binary, familiar
     to contributors/users. But PHP's `nikic/PHP-Parser` is a lossy AST with no trivia — we'd
     need a lossless layer (or use `PhpToken`/`token_get_all` + our own thin tree), and
     reimplement the Doc engine + preservation from scratch. Slower runtime, more to build.
  3. **New Rust engine, borrowing mago's parser only.** Middle path if we dislike coupling
     to mago's formatter but value its parser.

My lean: **option 1 (build on mago)** for engine quality/speed/time-to-value, *unless*
ecosystem fit (pure-composer install, PHP-contributor accessibility) is a hard requirement
— in which case option 2. This is the key strategic call and should be made explicitly
before any code. See `21-open-decisions.md`.
