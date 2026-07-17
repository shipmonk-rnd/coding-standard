# PoC — permissive whitelist formatter for PHP

Proof of concept for the from-scratch `shipmonk/coding-standard` rewrite.
Design: `../notes/40-architecture-plan.md` (and `00`–`04` for the principles).

## The model in one sentence

Per construct, a **template** defines the *set* of allowed layouts; the engine either
**MATCH**es the source (byte-identical no-op), **REPAIR**s it to the closest allowed
form, or goes **FATAL** (unsupported construct / comment placement — file returned
unchanged). Default-deny: nothing is silently allowed; no rule is width-based.

Vertical layout (line breaks, rows, blank-line grouping) is the author's choice and is
preserved; horizontal layout (spacing, indentation, trailing commas) is strictly
enforced.

## Run

```bash
php tests/run.php                      # golden-file suite (+ idempotency checks)
php bin/fmt.php [--check|--write] FILE # format a file (subset of PHP only)
```

Zero dependencies — pure PHP ≥ 8.1 (uses `PhpToken`).

## Supported subset (everything else = FATAL, by design)

`<?php` header (+ optional same-line `declare(strict_types = 1)`), expression
statements, unary/binary expressions over atoms, array literals (incl. `=>`, nesting),
function calls, own-line comments (statement level and as rows inside broken
collections).

## Layout of the code

| file | role |
|---|---|
| `src/Lexer.php` | significant tokens + whitespace gaps (comments are tokens) |
| `src/Parser.php` | layout parser = the default-deny totality gate |
| `src/CollectionLayout.php` | THE core template: flat/broken, rows, blank grouping, trailing comma iff broken |
| `src/FileNode.php` … `src/Atom.php` | per-construct templates (`render()` = projection + print) |
| `src/Verifier.php` | re-tokenize output, diff vs input modulo whitespace + trailing commas |
| `src/Formatter.php` | MATCH / REPAIR / FATAL entry point |
| `tests/fixtures/` | golden files: `X.php` (+ `.fixed` expected output / `.fatal` expected error) |
