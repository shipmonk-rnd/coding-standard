# PrettyPHP (lkrms/pretty-php) — reference notes

Repo: `/p/shipmonk/+tasks/oss-coding-standard-from-scratch/repos/pretty-php`
Version at HEAD: 2025-12-09 (`5d244a4b`). "The opinionated PHP code formatter",
written in pure PHP, inspired by Black. ~98 PHP files under `src/`.

TL;DR: It is a **token + rules** formatter (no AST, no Wadler Doc IR). Its most
surprising and most relevant property: **it is NOT width-based.** There is no
max-line-length / wrap-to-fit rule. Line breaks are driven by (a) newline
*preservation* from the input and (b) fixed structural rules — which is much
closer to what we want than the "opinionated" label suggests. Its opinionated-ness
is in *content* (quotes, spacing, alignment), not in width-based reflow.

---

## 1. Data model

**Tokens, not AST.** Built directly on PHP's native tokenizer.

- `Token::tokenize()` calls `parent::tokenize($code, \TOKEN_PARSE, ...)`
  (`src/Token.php:100-111`), i.e. `PhpToken::tokenize`. `Token extends
  GenericToken` which `extends PhpToken` (native, PHP >= 8) or a Salient polyfill
  on PHP 7.4 (`src/GenericToken.php:9-19`). So it's the standard PhpToken model,
  subclassed with rich formatter state.
- Filters run over the raw token stream before parsing
  (`src/Parser.php:56-62`, `Token::filter`). Filters (`src/Filter/*`) do
  normalization/removal on tokens, e.g. `RemoveWhitespace`, `RemoveEmptyTokens`,
  `SortImports`, `NormaliseKeywords`, `NormaliseCasts`, `TrimOpenTags`,
  `RemoveHeredocIndentation`.

**Document representation = an enriched doubly-linked list of tokens.** Each
`Token` carries navigation + structural links (`src/Token.php:40-56`):
`Prev/Next`, `PrevCode/NextCode` (skip whitespace/comments), `PrevSibling/
NextSibling`, `Parent`, `OpenBracket/CloseBracket`, `OpenTag/CloseTag`,
`Statement/EndStatement`, `Depth`, plus a `Data` array of lazily-computed
relationships (`src/Token.php:57-62`, catalog `Catalog/TokenData.php`). The
`Parser` (`src/Parser.php`, ~42KB) computes all these links — it is a
lightweight structural pass, not a full grammar/AST. There is also a thin
`Internal/Document.php` wrapper.

**Fidelity / whitespace / comments:**
- Whitespace is *not* a token in the output model. Instead each token has an
  integer `$Whitespace` bitmask of before/after flags
  (`src/Token.php:77`; catalog `src/Catalog/WhitespaceFlag.php`):
  `SPACE / LINE / BLANK` and their `NO_*` negations, each in BEFORE/AFTER and a
  `CRITICAL_*` (mandatory, wins conflicts) variant. Rules OR flags onto tokens;
  the Renderer resolves them. This is the core mechanism worth noting: **spacing
  is declared per-token as flags, then rendered**, rather than emitted imperatively.
- Indentation is also per-token integer state, not literal text:
  `TagIndent, PreIndent, Indent, HangingIndent, Deindent, LinePadding,
  LineUnpadding, Padding` (`src/Token.php:78-85`). Renderer sums them
  (`src/Renderer.php:163-166`, `341-345`) times `TabSize`.
- `OriginalText` / `ExpandedText` preserve source for tokens that were modified
  or had tabs expanded (`src/Token.php:64-73`); `column`, `index`, `OutputLine/
  OutputPos/OutputColumn` track positions (`src/Token.php:26-95`).
- **Comments are ordinary `T_COMMENT`/`T_DOC_COMMENT` tokens** kept inline in the
  linked list (so PrevCode/NextCode skip them), then normalized/placed by
  dedicated rules (see §3). Not attached as trivia on other nodes.

## 2. Layout algorithm — confirmed NOT width-based

**No max-line-length rule exists.** A full-tree grep for width/wrap/line-length
logic (`/tmp/w.txt`) finds only `column` usage for *alignment* (AlignComments,
AlignChains `LineUnpadding`) and position tracking — no comparison of a line
length against a limit, no configurable ruler (80/100/120 appear only as rule
*priority* numbers in `docs/Rules.md`). README explicitly frames it as diff-
minimizing, Black-inspired, but line-break decisions come from:

1. **Newline preservation** (`Rule/PreserveNewlines.php`, priority 200, default
   on). Line breaks adjacent to operators/delimiters/brackets are copied from
   input to output, gated by a per-token-type index of where breaks are *allowed*
   (`docs/Newlines.md`, validated by `tests/unit/TokenIndexTest.php`;
   `AbstractTokenIndex.php` / `TokenIndex.php`). `--operators-first/-last`,
   `--ignore-newlines` tune this. So "should this list/expression be multi-line?"
   is answered by *"was there a newline in the source?"*, not *"does it fit in N
   columns?"*.
2. **Fixed structural rules** — e.g. `StrictLists`, `StrictExpressions`,
   `VerticalSpacing`, `ControlStructureSpacing`, `StatementSpacing`,
   `PlaceBraces`, `PlaceBrackets`: unconditional "always break here / never here"
   based on token role, not measurement. `docs/Rules.md:318` even notes a rule
   only breaks expressions that *already* break — i.e. presence-of-newline logic,
   not fit logic.

**Architecture = token-rules, multi-pass, priority-ordered.** Not Wadler/Doc IR.
- Rules implement contracts (`src/Contract/*Rule.php`: `TokenRule`,
  `StatementRule`, `ListRule`, `BlockRule`, `DeclarationRule`) and declare a
  numeric priority + which token types they touch.
- `Formatter::format()` (`src/Formatter.php:633`) parses to `$this->Document`
  (`:694`), then applies rules in priority order across a few passes: content
  normalization (0-99) -> horizontal/vertical whitespace (100-299) ->
  indentation & alignment-callback registration (300-399) -> preset styles
  (400-499) -> block processing (500-599) -> alignment/other callbacks (600-699)
  -> finalise / `beforeRender()` (900-999) (`docs/Rules.md:7-16`;
  `src/Formatter.php:1032-1048`). Alignment (which *does* read columns) is done
  late via registered callbacks that pad tokens.
- `Renderer::render()` (`src/Renderer.php:51`) then walks the token list once
  and emits text from the resolved `$Whitespace` flags + indent integers.

**Correctness guard (nice idea to steal):** after formatting, the output is
re-tokenized and compared token-for-token (id + text, whitespace-insensitive)
against the input via `Token::tokenizeForComparison` /
`tokenizeAndSimplify` (`src/Formatter.php:1266-1279`; `Token.php:117-127`) so a
formatting bug that changes code (not just whitespace) throws instead of
corrupting the file. `tokenizeForComparison` uses cheap `GenericToken`s.

## 3. Reusable PHP-specific handling (despite it being canonical)

Even though its *break-decision core* isn't our model, its PHP-token plumbing is
directly instructive:

- **The token abstraction itself** — subclassing `PhpToken` and hanging
  precomputed structural links (`PrevCode/NextCode`, `OpenBracket/CloseBracket`,
  `PrevSibling/NextSibling`, `Parent`, `Depth`, `Statement`) off each token
  (`src/Token.php:40-56`, computed in `src/Parser.php`). This "linked-list +
  sibling/bracket pointers" is a strong, cheap substitute for an AST for
  localized whitespace edits — very aligned with our localized-edit goal.
- **Whitespace-as-flags with a NO_/CRITICAL_ precedence lattice**
  (`Catalog/WhitespaceFlag.php`) — a clean way to let multiple independent rules
  express spacing intent and deterministically resolve conflicts. Good pattern
  for a permissive formatter where several small rules touch the same gap.
- **Heredoc / nowdoc** — genuinely tricky, handled across several places:
  `Filter/RemoveHeredocIndentation.php`, `Rule/FormatHeredocs.php` (runs twice:
  priority 62 and a `beforeRender` pass at 980, `docs/Rules.md:580-582`),
  `Token::$Heredoc`/`$HeredocIndent`, indentation strategy enum
  `Catalog/HeredocIndent.php` (NONE/LINE/MIXED/HANGING), and Renderer heredoc
  re-indentation via regex on embedded newlines (`src/Renderer.php:116-128`).
  Body content must be preserved byte-exact except leading indentation.
- **Mixed HTML/PHP** — `T_INLINE_HTML` plus open/close tags: `Filter/
  TrimOpenTags.php`, `Token::$OpenTag/$CloseTag`, and a separate `TagIndent`
  indent channel (`src/Token.php:78`, `src/Renderer.php:163-194`) so PHP inside
  HTML indents relative to its open tag. Handled by `StandardSpacing`,
  `PlaceComments`, `DeclarationSpacing`.
- **Comment / docblock handling** — a whole cluster: filters `MoveComments`,
  `RemoveEmptyDocBlocks`, `TruncateComments`; rules `NormaliseComments`
  (priority 40), `PlaceComments` (126), `AlignComments` (500). Distinguishes
  `//`, `#`, `/* */`, `/** */`; reindents multi-line comment bodies in the
  Renderer (`src/Renderer.php:316-349`); moves comments only when needed for
  correct placement of adjacent tokens; won't move a trailing same-line comment
  to the next line (README "Pragmatism"). This is exactly the fiddly comment
  logic we'll also need.
- **Strings/numbers** — `NormaliseStrings`, `NormaliseNumbers`, `ProtectStrings`
  and matching filters normalize quote style / numeric literals but re-tokenize
  to prove equivalence. (For us these are opt-in content changes, likely out of
  scope, but the escaping/UTF-8 handling in `docs/Rules.md:109-110` is a good
  reference for edge cases.)
- Uses `\TOKEN_PARSE` flag so tokenization understands modern syntax (up to PHP
  8.4 incl. property hooks).

**Tricky bits it flags that we'll also hit:** heredoc/nowdoc indentation &
byte-fidelity; inline HTML indentation relative to open tags; comment placement
vs adjacent-token correctness (moving comments can change meaning); tab
expansion / soft-tabs; `T_OPEN_TAG` whitespace trimming; ensuring edits never
change the token stream (equivalence check).

## 4. License & maintenance

- **License: MIT** (`LICENSE`, `composer.json:5`, "Copyright (c) Luke Arms").
  MIT is permissive — we could study/adapt patterns freely with attribution.
- **Actively maintained.** HEAD commit 2025-12-09; targets PHP 7.4–8.4; ships as
  a signed PHAR, PHIVE, AUR, Homebrew, and an official VS Code extension. Single
  primary author (Luke Arms / lkrms). Depends heavily on his own `salient/*`
  libraries (`composer.json`), pinned to exact `0.99.81` — a coupling to note if
  we ever tried to reuse code directly (we won't; we're learning patterns).

---

### Bottom line for our project
PrettyPHP confirms a pure-PHP full formatter is viable on the native tokenizer
with a linked-token model and no AST. Its break engine is **newline-preserving +
fixed structural rules, not width-based** — so unlike Prettier/Black-proper, its
core *is* broadly compatible with our "no width rules, permissive, localized
edits" design. What differs is its *canonical* stance (one form for spacing,
quotes, alignment) vs our *permissive* one (accept multiple valid forms, only
fix out-of-spec). Steal: the token+links data model, whitespace-as-flags with a
NO_/CRITICAL_ precedence lattice, the per-token integer indent channels, the
re-tokenize-and-compare equivalence guard, and its heredoc/HTML/comment handling.
Don't adopt: its "ignore previous formatting" opinionation.
