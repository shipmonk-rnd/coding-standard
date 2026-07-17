# PHP-CS-Fixer architecture study

Repo: `/p/shipmonk/+tasks/oss-coding-standard-from-scratch/repos/PHP-CS-Fixer` (v3.x, requires PHP `^7.4 || ^8.0`).
Goal: validate the token+fixer model for our pure-PHP, no-width, localized-edit, permissive formatter.

---

## 1. Token model — `Tokens` collection over native `token_get_all`

**It is NOT an AST. It is a flat, mutable array of tokens** produced by PHP's own tokenizer.

- `Tokens extends \SplFixedArray<Token>` — a flat indexed list (`src/Tokenizer/Tokens.php:54`, `@extends \SplFixedArray<Token>` at `:31`).
- Built from PHP's native `token_get_all($code, \TOKEN_PARSE)` — `Tokens::setCode()` at `src/Tokenizer/Tokens.php:1172`. It wraps each raw prototype in a `Token` (`:1179-1181`). It does **not** use `PhpToken` objects; it uses the legacy `array{id, text, line}` / `string` prototypes.
- `Token` (`src/Tokenizer/Token.php:36`) is `@readonly` and holds exactly three fields: `content` (string), `id` (`?int`, null for single-char tokens like `(`), `isArray` (bool). Constructor accepts either an array prototype `[id, content, line?]` or a bare string (`:56-89`). Immutable: you never edit a token in place, you replace it with a new `Token`.

**Full fidelity — why unchanged code stays byte-identical:**
- Whitespace is a real token (`\T_WHITESPACE`), comments are `\T_COMMENT` / `\T_DOC_COMMENT` — everything from the source is a token, nothing is discarded. `Token::isWhitespace()` (`:462`), `isComment()` (`:393`).
- Rendering back = plain concatenation of every token's `content`: `generateCode()` → `generatePartialCode()` just does `$code .= $this[$i]->getContent()` over the range (`src/Tokenizer/Tokens.php:617-642`). No pretty-printer, no re-layout. If no token was replaced, output is identical to input byte-for-byte.
- Guard: `setCode()` early-returns if `$code === $this->generateCode()` (`:1155`), and the empty-token/hash machinery avoids spurious churn.

**Mutation / insert / remove primitives (all on the flat array):**
- Replace: `$tokens[$i] = new Token(...)` via overridden `offsetSet` (`:395`) — flips the `changed` flag only if the new token differs (`!$this[$index]->equals($newval)`, `:420`), and maintains block-edge and found-token caches.
- Remove: `clearAt($index)` sets an **empty** placeholder token `new Token('')` (`:1088-1091`) rather than shifting the array; empties are compacted later by `clearEmptyTokens()` (`:450`). `clearTokenAndMergeSurroundingWhitespace()` (`:1318`) removes a token and fuses the whitespace on either side — the standard "delete cleanly" helper.
- Insert: `insertAt()` → `insertSlices()` (`:987`, `:1011`) — batches multiple insertions in one pass for performance; indices are relative to the pre-insert array.
- Range replace: `overrideRange($start, $end, $items)` (`:1100`) — overwrites a span with a new token list, padding with `__PLACEHOLDER__` tokens or clearing extras as needed. This is how block-level rewrites (e.g. class reordering) are done.
- Whitespace normalization helper: `ensureWhitespaceAtIndex($index, $offset, $whitespace)` (`:511`) — the workhorse for "one space here" / "newline here"; replaces an existing WS token or inserts a new one, and cleverly folds trailing whitespace into a preceding `T_OPEN_TAG`.

Change tracking is a single collection-level bool `$changed` (`:142`), exposed via `isChanged()` (`:1076`) and reset by `clearChanged()`. Every mutating op also invalidates `codeHash`/`collectionHash`/block caches.

**Extra token kinds:** after tokenizing, `applyTransformers()` (`:1361`) runs "transformers" that split/relabel ambiguous native tokens into custom kinds (`CT::*` constants) — e.g. `[` becomes `CT::T_ARRAY_BRACKET_OPEN` vs plain index bracket, `{` gets disambiguated into brace/dynamic-prop/etc. See the block-edge definitions map at `src/Tokenizer/Tokens.php:275-347`. This gives fixers reliable structural anchors without a parser.

---

## 2. Fixer model — one rule = one `FixerInterface`

`FixerInterface` (`src/Fixer/FixerInterface.php:26`) is small:
- `isCandidate(Tokens): bool` — cheap bloom-filter check ("does this file even contain the token kinds I care about?"); false ⇒ definitely skip (`:37`).
- `fix(\SplFileInfo, Tokens): void` — mutate the token collection in place (`:52`).
- `isRisky()`, `getName()`, `getPriority()` (higher runs first, `:73`), `supports(file)`, `getDefinition()`.

`AbstractFixer` (`src/AbstractFixer.php:30`) provides the template: `fix()` is `final` and only calls the subclass's `applyFix()` when `count>0 && isCandidate() && supports()` (`:58-67`). Subclasses implement `abstract protected applyFix()` (`:98`). Name is auto-derived from the class name (`FooBarFixer` → `foo_bar`, `:41-43`). Configurable fixers add `ConfigurableFixerInterface` + `createConfigurationDefinition()`; whitespace-sensitive ones add `WhitespacesAwareFixerInterface` (indent + line-ending come from `WhitespacesFixerConfig`, default 4 spaces + `\n`, `:105`).

**How a fixer finds constructs and edits locally:** it scans the token array (typically **backwards**, `for ($index = count-1; $index >= 0; --$index)`, so insertions don't invalidate not-yet-visited indices), matches token kinds with `isGivenKind()` / `equals()`, walks with helpers like `getNextMeaningfulToken()` / `getPrevMeaningfulToken()` / `findBlockEnd()`, and edits only the matched span.

**Example A — `TrailingCommaInMultilineFixer`** (`src/Fixer/ControlStructure/TrailingCommaInMultilineFixer.php`):
- `isCandidate`: `isAnyTokenKindsFound([T_ARRAY, CT::T_ARRAY_BRACKET_OPEN, '(', CT::T_DESTRUCTURING_BRACKET_OPEN])` (`:112`).
- `applyFix` scans backwards, classifies each `(`/`[` by looking at the previous meaningful token (array vs list vs function call vs params vs match) (`:149-215`), then `fixBlock()`.
- `fixBlock` (`:218`) finds the block end via `findBlockEnd()`, and **only acts if the block is multiline** (`isBlockMultiline` + `isPartialCodeMultiline`, `:222-232`). The edit is a single localized `insertAt($beforeEndIndex+1, new Token(','))` (`:241`). Nothing else in the block is touched.

**Example B — `ArraySyntaxFixer`** (`src/Fixer/ArrayNotation/ArraySyntaxFixer.php`) — long↔short array:
- `fixToShortArraySyntax` (`:126`): replaces the `(`/`)` tokens with `[`/`]` tokens and clears the `array` keyword via `clearTokenAndMergeSurroundingWhitespace()`.
- `fixToLongArraySyntax` (`:114`): reverse — swap brackets and `insertAt` a `T_ARRAY` token. Both are pure token-level swaps; inner content and its whitespace are preserved untouched.

---

## 3. No width / no line-length — CONFIRMED

**There is no width-based, column-counting, or line-wrapping engine anywhere.** Layout decisions are driven by (a) the form the user already wrote, (b) local whitespace normalization, and (c) structural/count-based triggers — never by an 80/120 column budget.

- Directory sweep found **no** `LineLength` / `MaxWidth` / `Wrap` / `Column` fixer. Grepping fixer sources for `line_length` / `maxLineLength` / `columns` yields nothing relevant.
- The only "multiline" primitive is `Tokens::isPartialCodeMultiline()` (`src/Tokenizer/Tokens.php:1295`) which just checks whether any token content in a range `str_contains("\n")` — a boolean "did the user already break this across lines?", **not** a width measurement.
- Fixers that produce multi-line output are gated on a **structural count** or on the code **already being multi-line**, not on width (see §4).

**Only near-exceptions (still not width-based):**
- `NonPrintableCharacterFixer` — encoding cleanup, unrelated.
- `MultilinePromotedPropertiesFixer` — splits promoted constructor properties onto separate lines, triggered by *parameter count* ≥ `minimum_number_of_parameters` (a count threshold), never by width (see §4).

This is the key architectural validation for us: a token-stream + localized-edit formatter with count/structure triggers needs no width model.

---

## 4. Multiline vs single-line — the "multiple allowed forms" pattern

This is exactly our permissive model, and PHP-CS-Fixer already implements it in two distinct flavours.

**Flavour 1 — preserve the user's form, only normalize consistency within it.**
`TrailingCommaInMultilineFixer` (§2): detects the form with `TokensAnalyzer::isBlockMultiline()` + `Tokens::isPartialCodeMultiline()` (`:222`, `:230`). If single-line ⇒ does nothing (single-line stays as-is, no trailing comma). If multi-line ⇒ ensures a trailing comma. It never converts one form to the other. Its sibling `NoTrailingCommaInSinglelineFixer` handles the single-line case symmetrically. Net effect: **both `[1, 2]` and the multi-line array are accepted and preserved**; each just gets its own local normalization.

`MethodArgumentSpaceFixer` (`src/Fixer/FunctionNotation/MethodArgumentSpaceFixer.php`) makes this explicit with an `on_multiline` option: `'ignore' | 'ensure_single_line' | 'ensure_fully_multiline' | 'ensure_single_line_for_single_argument'` (`:39`, `:214`). The `ensure_fully_multiline` branch fires **only when the call is already multi-line** (`$isMultiline` computed by scanning for newline-containing whitespace between args, `:241-261`), then enforces "one argument per line + first arg on its own line". Detection = "is there a `\n` in the whitespace tokens inside the parens", again purely structural.

**Flavour 2 — structural/count trigger to switch forms (our "forced" reformat).**
`MultilinePromotedPropertiesFixer` (`src/Fixer/FunctionNotation/MultilinePromotedPropertiesFixer.php`):
- `shouldBeMultiline()` (`:164`): a promoted param exists AND promoted-param count ≥ `minimum_number_of_parameters` ⇒ split onto multiple lines.
- `shouldBeSingleline()` (`:186`): a promoted param exists AND currently multiline AND param count < minimum ⇒ collapse to one line.
- Transform: `makeMultiline()` (`:241`) inserts newline+indent whitespace tokens after each argument-separating comma using the whitespace config; `makeSingleline()` (`:222`) replaces the inter-argument whitespace tokens with single spaces / nothing. The switch is driven by a **count threshold**, never by measuring line width.

**Mechanics to steal:** form detection = "scan the whitespace tokens inside the block for a newline" (`isPartialCodeMultiline`). Transform single→multi = replace/insert `T_WHITESPACE` tokens carrying `"\n" + indent`; multi→single = replace those whitespace tokens with `' '` or empty. Because indentation and EOL come from `WhitespacesFixerConfig`, the transform is deterministic and localized.

---

## 5. Comments & docblocks

- At the token layer, comments are ordinary tokens: `T_COMMENT` (line/block) and `T_DOC_COMMENT` (PHPDoc). `Token::isComment()` (`src/Tokenizer/Token.php:393`). They are never dropped, so comment-only edits are localized token replacements.
- For structured PHPDoc editing there's a dedicated mini-model under `src/DocBlock/`: `DocBlock` (`src/DocBlock/DocBlock.php:30`) parses a single `T_DOC_COMMENT`'s text into a `list<Line>` by regex-splitting on line boundaries (`:54`), and lazily into `Annotation` objects (`@param`, `@return`, …). Supporting classes: `Line`, `Annotation`, `Tag`, `TypeExpression`, `TagComparator`, `ShortDescription`.
- Editing flow for docblock fixers: read the doc-comment token's content → build a `DocBlock` → mutate lines/annotations → `(string) $docBlock` (`__toString` → `getContent()`, `:62`) → write back as a single replacement `Token([T_DOC_COMMENT, $newText])`. So docblock internals are edited as text within one token, while the token stream around it is untouched. This is a **second, localized representation layered on top of a single token** — a useful pattern (don't try to tokenize inside comments).

---

## 6. Idempotency & safety

**Important correction to a common assumption: the runner does NOT loop fixers to a fixed point.** `Runner::fixFile()` runs each fixer **exactly once**, in priority order, in a single `foreach ($this->fixers as $fixer)` pass (`src/Runner/Runner.php:620-670`). After each fixer, if `$tokens->isChanged()` it compacts empties, records the fixer, and resets the changed flag (`:654-657`).

Convergence is therefore guaranteed by two things instead of a loop:
1. **Priority ordering** — fixers are pre-sorted so that a fixer never produces output another earlier-run fixer would want to re-touch. Fixer docblocks declare `Must run before/after X` (e.g. `TrailingCommaInMultilineFixer::getPriority` note at `:105`; MPP "Must run before BracesPositionFixer, TrailingCommaInMultilineFixer").
2. **Per-fixer idempotency enforced by the test harness**, not at runtime: `AbstractFixerTestCase::doTest()` runs the fixer on the input and asserts the output equals `$expected`, then runs the fixer **again on `$expected`** and asserts it does **not** change and `isChanged()` is false (`tests/Test/AbstractFixerTestCase.php:497-552`). Every fixer must be individually idempotent to pass CI.

Change detection at file level uses **code hashes**, not the dirty flag, because two fixers can cancel each other out: `$oldHash !== $newHash` decides whether to write (`src/Runner/Runner.php:686-694`).

**Parse-safety (output is still valid PHP):**
- A `LinterInterface` (`src/Linter/LinterInterface.php`) checks that source parses. Two impls: `TokenizerLinter` (`src/Linter/TokenizerLinter.php:44`) tries `Tokens::fromCode()` and catches `\ParseError`/`\CompileError`; `ProcessLinter` shells out to `php -l`. `CachingLinter` memoizes.
- The file is linted **before** fixing (`fixFile` opens with `$lintingResult->check()`, `:557`) and the produced source is linted **after** (`$this->linter->lintSource($new)->check()`, `:701`) before writing — if the fixers produced invalid PHP the file is reported as an error and left unwritten (`:702-708`). With `PHP_CS_FIXER_DEBUG=1` it also lints after every individual fixer (`:658-668`) to pinpoint the culprit.

---

## 7. Assessment for our tool

**Verdict: the token + fixer architecture is an excellent base model for us — keep the core, redesign the rule contract for permissiveness.**

**Keep:**
- **Flat, immutable-token, mutable-collection model over `token_get_all`.** Render = concatenate `content`. This is precisely what gives byte-identical output for unchanged spans and makes truly localized edits trivial. This is the single most important idea to copy.
- **`isCandidate` bloom-filter + backwards scan + meaningful-token navigation + block-edge cache.** Cheap, robust, no parser needed for the vast majority of rules.
- **Whitespace/comments as first-class tokens**, edited via a small set of primitives (`ensureWhitespaceAtIndex`, `clearTokenAndMergeSurroundingWhitespace`, `insertSlices`, `overrideRange`). Adopt these verbatim in spirit.
- **Custom token kinds via a transformer pass** (`CT::*`) to disambiguate `[`/`{`/`(` — this is what lets token-level rules stay reliable.
- **Idempotency-as-a-test-invariant + hash-based change detection + pre/post lint.** Cheap and effective.
- **The `on_multiline` / count-threshold pattern (§4) is already our "multiple allowed forms" model.** `isPartialCodeMultiline` (newline-in-whitespace check) is the exact primitive for "detect the user's chosen form".

**Do differently:**
- **Make permissiveness the default contract, not an opt-in flag.** In PHP-CS-Fixer each fixer picks one canonical output (e.g. `syntax => short`); permissiveness only appears where someone added an `ignore`/`on_multiline` option. Our rule interface should be shaped as "given a construct, is its current form one of the allowed forms? if yes, do nothing; if no, snap to the nearest allowed form" — so multi-form acceptance is structural, not per-rule bolt-on.
- **Drop the priority-list-instead-of-loop compromise.** Their single-pass + hand-tuned priorities + per-fixer idempotency is fragile (long "must run before/after" chains). Since we do localized, non-conflicting edits, consider either a genuine fixed-point loop with a convergence guard, or a phase model, so rule authors don't hand-maintain a global priority order.
- **Formalize the "form detection" helper.** They re-derive multiline-ness ad hoc per fixer. We should expose one blessed API (block form = single-line | multi-line, item count, etc.) so every rule detects forms identically.
- **Reuse the DocBlock-style "sub-model on a single token" idea** for any place where structure lives inside one token's text.

**Token-only vs needing an AST — for structural rules like class-member ordering:**
- **The token-only model is sufficient**, demonstrated by `OrderedClassElementsFixer` (`src/Fixer/ClassNotation/OrderedClassElementsFixer.php`). It locates the class body `{`, uses `TokensAnalyzer::getClassyElements()` (`src/Tokenizer/TokensAnalyzer.php:57`) to enumerate members with their `start`/`end` token indices, sorts the element metadata, and — critically — moves whole token slices intact: `sortTokens()` clones each element's `[start..end]` token span (comments and whitespace included) and `overrideRange()`s the class body (`:623-635`). No AST; full fidelity of each moved member preserved.
- Structural analysis is centralized in `TokensAnalyzer` (890 lines, `src/Tokenizer/TokensAnalyzer.php`) — a lightweight "structural layer over tokens" (class elements, visibility, block multiline, function args…). This is the "light structural/tree layer on top of tokens" we discussed, and it confirms we do **not** need a full parser/AST for member ordering, visibility, argument handling, etc.
- Where an AST *would* help: deep semantic rules (type-aware, cross-reference, risky refactors). PHP-CS-Fixer marks those `isRisky()` and still does them on tokens, accepting the complexity. For a formatter (non-risky, layout-only) the token + `TokensAnalyzer`-style structural layer is the right altitude.
