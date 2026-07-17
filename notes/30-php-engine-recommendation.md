# Consolidated PHP-engine recommendation

All 7 formatters researched (`10`–`18`). Decisions locked: pure formatter (`01`), pure
PHP/composer no binary, no width rules (`02`), core invariant = preserve vertical layout /
normalize horizontal whitespace (`03`). This note picks the engine.

## The evidence, condensed
- **php-cs-fixer** (`16`): flat mutable token collection over native `token_get_all`;
  whitespace+comments are **first-class tokens**; rendering = concatenate token contents
  → unchanged spans byte-identical. No width model. "Multiple forms" already exists
  (preserve-and-normalize + count-threshold). **Token-only is sufficient for structural
  rules** (member ordering via a `TokensAnalyzer` structural layer over tokens — no AST).
- **PrettyPHP** (`18`): pure-PHP, **not width-based** (newline *preservation* + structural
  rules), `PhpToken` subclass + **precomputed structural links** (Parent/Depth/OpenBracket/
  Siblings/Statement) = a token "CST-lite"; re-tokenize equivalence guard. Proves the whole
  approach works in production PHP.
- **nikic/PHP-Parser** (`17`): excellent AST + format-preserving printer, but preserves
  **structure not trivia** — whitespace rules (our bulk) don't map to AST mutations. AST
  hides the very thing a formatter edits. Great parser, wrong primary abstraction for us.
- **The Rust/CST crowd** (`10`–`15`): their core (Wadler Doc width-fitting engine) is
  **not applicable** (no width rules). Only their non-width ideas transfer: trivia model,
  comment classification, source-signal-as-trigger, re-parse verification.

## RECOMMENDATION: token-based "CST-lite" engine (no AST, no parser dependency)

Build the engine on PHP's **native tokenizer** (`PhpToken::tokenize()` / `token_get_all`),
which means **zero heavy dependencies** — pure composer, hackable, satisfies D2/D3.

Combine the best of the two proven pure-PHP models:
1. **Data model — from php-cs-fixer:** a flat, mutable token list where **whitespace and
   comments are first-class tokens**. This is the RIGHT default for a *preserve-first*
   permissive formatter: "leave it alone" = don't touch the token (byte-identical output
   for free). (PrettyPHP's whitespace-as-flags model assumes regenerating all whitespace —
   that's the canonical approach we reject.)
2. **Structural navigation — from PrettyPHP:** precompute per-token links (matching
   bracket, parent, depth, prev/next sibling, enclosing statement) once, so structural
   rules (member ordering, `[]` vs `array()`) are clean without a parser. (php-cs-fixer
   computes this on demand via `TokensAnalyzer`; precomputing à la PrettyPHP gives nicer
   rule code — pick per taste, both work.)
3. **Bracket disambiguation — from php-cs-fixer:** a transformer pass assigning custom
   kinds to overloaded `[`/`{`/`(` (array vs index vs block vs call vs …) so token rules
   stay reliable.
4. **Verification — from PrettyPHP/CSharpier:** after formatting, re-tokenize output and
   compare the non-whitespace token stream to the input; reject if anything but whitespace/
   allowed-token-moves (trailing comma add/remove) changed. Guarantees we never alter
   semantics.

### Why not the alternatives
- **nikic AST (hybrid)**: adds a dependency and a second model, and its strength (structural
  format-preservation) is redundant once we confirm token-only handles structural rules;
  its weakness (trivia) is exactly our main workload. Reconsider ONLY if we later want deep
  semantic/type-aware rules — but those are lint (delegated to PHPStan), out of scope.
- **Full from-scratch lossless CST**: the token CST-lite already gives lossless fidelity
  (whitespace/comments are tokens) + structural navigation (links). A separate node tree
  buys little for a layout-only tool and is much more work.

## Rule contract (the key improvement over php-cs-fixer)
Make **permissiveness the default contract**, not a per-rule opt-in:
> Each rule answers "is the construct's CURRENT form in the allowed set? If yes, do
> nothing. If no, snap it to the nearest / preferred allowed form."

- Default action is **no-op** (preserve). A rule only edits on genuine violation.
- Rules touch **horizontal whitespace** freely; touch **vertical layout** only via explicit
  structural triggers (trailing comma applicability, count-based multiline), never width.
- Replace php-cs-fixer's fragile hand-tuned priority list with a **phase model +
  convergence loop** (run to a fixed point) and enforce per-rule idempotency in tests.

## Engine skeleton (proposed)
```
source ──tokenize──▶ Token[] (ws/comments first-class)
        ──analyze──▶ + structural links + disambiguated bracket kinds   (the "CST-lite")
        ──rules───▶ each rule: detect violation → localized token edits (preserve by default)
        ──render──▶ concatenate token contents  (unchanged spans byte-identical)
        ──verify──▶ re-tokenize & diff vs input (semantics unchanged) else reject
```

## Remaining open items
- **D6** config schema: per-construct "allowed forms" + "preferred form when forced".
- **D7** the actual standard content: enumerate allowed forms + structural triggers per
  construct (arrays done in `03`; do calls/params/match/chains/use/attributes/members).
- PHP-specific hard cases to budget for (from `14`/`18`): heredoc/nowdoc reindent, mixed
  inline-HTML/PHP boundaries, comment/docblock normalization & placement, attributes.
- Suggest a **thin proof-of-concept**: tokenizer + CST-lite links + 2 rules (array bracket
  spacing/trailing-comma exercising the invariant, and one structural rule) + verification,
  on the existing golden-file fixtures (`01`). Validates the engine before scaling rules.
```
