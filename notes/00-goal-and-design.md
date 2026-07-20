# Goal & Design — shipmonk coding-standard rewrite

## High-level goal

Rewrite https://github.com/shipmonk-rnd/coding-standard **from scratch**, WITHOUT depending on:
- `slevomat/coding-standard`
- `squizlabs/php_codesniffer` (PHP_CodeSniffer / phpcs)

## Core design principle — "permissive formatter"

The coding standard must NOT enforce exactly one way to write code. For any formatting
choice where multiple styles are reasonable, **allow all of them**, and only reformat when
the code uses a style that is *not* in the allowed set.

### Canonical example — array formatting

Both of these are equally allowed and neither is rewritten into the other:

```php
$a = [1, 2];
```

```php
$a = [
  1,
  2,
];
```

Contrast with typical formatters (Prettier, gofmt, php-cs-fixer in fixed mode) which pick
ONE canonical form and rewrite everything to it. We want a formatter whose "fixed point"
is a *set* of acceptable forms, not a single normal form.

### Implications / open questions this raises

- The tool is closer to a **linter with autofix** than a pure formatter — but the "lint
  rules" are about formatting, and violations are auto-fixable.
- Idempotency: running twice must not change already-allowed code. (Same as any formatter.)
- Stability: given allowed input, output == input (no churn). This is the whole point.
- When code IS out of spec, which allowed form do we rewrite it to? (Need a "preferred"
  form per rule, used only when reformatting is forced.)
- Config surface: each rule needs to express "these forms are allowed" rather than
  "this is the one true form".

## Key architectural question (undecided)

**AST-based vs raw-token-based formatter?**

- Token-based (like phpcs): operates on the token stream, preserves original formatting by
  default, edits are localized. Good fit for "only change what's wrong". Historically what
  phpcs/slevomat do — which is exactly what we're replacing, so understand *why* before
  copying.
- AST/CST-based (like Prettier, Biome, Roslyn): parse to a tree, then pretty-print. Pretty-
  printing inherently imposes ONE canonical form, which fights our core principle unless we
  use a lossless CST that preserves original trivia/whitespace and only rewrites subtrees
  that violate rules.
- Middle ground: **lossless CST** (concrete syntax tree preserving all trivia) — this is
  what modern formatters (Biome/Rome, Roslyn, oxc, tree-sitter tools) use. Lets us reason
  structurally AND preserve original formatting where it's already acceptable.

This is the thing the formatter-architecture research (oxc, Ruff, dprint, + others) must
inform. See 10-*.md research notes.

## Research plan

Research modern formatters, focusing on: IR/data model (AST vs CST vs token), how they
decide line breaks (the "Wadler/Prettier IR" doc-builder algebra vs hand-rolled), how they
preserve or normalize existing formatting, and whether any support "multiple allowed forms".

Targets: oxc formatter, Ruff formatter, dprint, plus Biome, Prettier, gofmt, rustfmt,
Roslyn/CSharpier, tree-sitter/Topiary. See notes 10..19.
