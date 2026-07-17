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

- header (+ optional same-line `declare(strict_types = 1)`), `namespace`, `use`
  (class/function/const/alias), attributes `#[...]`
- class/interface/trait/enum declarations: constants, properties, enum cases,
  methods (Allman brace; **2+ params force one-per-line multiline signature** —
  count-based trigger, not width), promoted constructor properties, trait use
- control flow: if/elseif/else, while, do-while, for, foreach, switch, try/catch/
  finally, match (always one arm per line); multiline conditions in the existing
  standard's leading-operator shape
- expressions: unary/binary/assignment chains (per-joint break preservation,
  leading operators), ternary (flat or broken), access chains with `->`-joint break
  preservation, closures / arrow fns, `new`, casts, clone/yield/print/include,
  static/global var statements, named arguments, heredoc/nowdoc + interpolated
  strings (verbatim passthrough)
- comments: own-line (statement level, collection rows), trailing same-line,
  docblock re-indent

Corpus status (see git log): the old coding-standard repo formats with **0 fatals**
(its 7 compliant sniff sources pass through **byte-identical**); foreign codebases
(php-cs-fixer, PHP-Parser, pretty-php — 1000+ files) run at ~95% coverage with zero
verifier/idempotency failures.

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
