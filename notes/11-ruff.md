# Ruff Formatter Architecture

Research into `astral-sh/ruff` (Python linter + formatter in Rust), focused on the
formatter and what is relevant to building a NEW formatter that allows **multiple valid
formatting forms** and only reformats out-of-spec code.

Repo: `/p/shipmonk/+tasks/oss-coding-standard-from-scratch/repos/ruff`

Relevant crates:
- `crates/ruff_formatter` — language-agnostic IR + printer (derived from Rome/Biome; see lineage below).
- `crates/ruff_python_formatter` — Python-specific `Format` rules built on top of `ruff_formatter`.
- `crates/ruff_python_trivia` — comment ranges, tokenizer for trivia (commas, parens, whitespace).
- `crates/ruff_python_parser` / `crates/ruff_python_ast` — parser and AST (shared with the `ty` type checker).

---

## 1. Data model: AST, not CST or token stream

Ruff formats from a **full AST** produced by `ruff_python_parser`, *plus* a side channel of
**trivia** (comment ranges, parenthesized ranges) and access to the original **token stream**
and **source string**. It is not a lossless CST (comments/whitespace are not AST nodes); instead
comments are re-attached to AST nodes by a heuristic pass.

Entry point `format_module_source` — `crates/ruff_python_formatter/src/lib.rs:136`:

```rust
let parsed = parse(source, ParseOptions::from(source_type))?;   // AST
let trivia = TriviaRanges::from(parsed.tokens());               // comment/paren ranges from tokens
let formatted = format_module_ast(&parsed, &trivia, source, options)?;
Ok(formatted.print()?)
```

`format_node` (`lib.rs:156`) builds a `Comments` map from the AST + source + trivia, then formats:
```rust
let comments = Comments::from_ast(parsed.syntax(), source_code, trivia);
let formatted = format!(PyFormatContext::new(options, source, comments, trivia, parsed.tokens()),
                        [parsed.syntax().format()])?;
formatted.context().comments().assert_all_formatted(source_code);  // every comment must be emitted
```

Key point: the format context holds `options`, the raw `source` string, the `comments` map, the
`trivia`, and the `tokens`. So while the primary structure is the AST, formatting rules
frequently **re-scan the original source text/tokens** for local decisions (e.g. "is there a
trailing comma here?", "is this expression parenthesized in the source?"). This is the main
mechanism by which user intent is recovered — see sections 4 and 5.

### Comment attachment: leading / trailing / dangling

Design doc is at the top of `crates/ruff_python_formatter/src/comments/mod.rs:1-114`. Comments
are associated **per node** (not per token — deliberate simplification), each classified as one
of three positions:

- **Leading** — comment before the node (`# comment` on its own line above, or before the node start).
- **Trailing** — comment at the end of a node (e.g. `a,  # trailing comment of a`).
- **Dangling** — neither leading nor trailing; belongs to the node but has no sub-node to attach
  to, e.g. a comment alone inside empty brackets `[ # dangling ]`.

`SourceComment` (`comments/mod.rs:118`) stores a `SourceCodeSlice` (range into source), a
`line_position` (own-line vs end-of-line), and a `Cell<bool> formatted` flag used to assert every
comment was emitted exactly once (`assert_all_formatted`).

Attachment pipeline:
- `comments/visitor.rs` — a preorder AST visitor (`CommentsVisitor` / `CommentsMapBuilder`)
  walks the tree and, for each comment, decides which node and which position.
- `comments/placement.rs` (**2462 lines** — the bulk of the comment logic) — the heuristics
  (`place_comment` and dozens of `handle_*` functions) that reassign comments to the semantically
  correct node/position. This is where Ruff works around AST limitations (e.g. the `/`
  positional-only separator has no node, so its comment is placed as a dangling comment of the
  `Arguments` node — described in the `mod.rs:67-89` "Limitations" note).
- `comments/map.rs` — `MultiMap` / `LeadingDanglingTrailing` storage keyed by node identity
  (`node_key.rs` uses pointer-identity `NodeRefEqualityKey`).

How comments flow into output: the generic `FormatNodeRule::fmt` wrapper
(`lib.rs:52-96`) automatically emits leading comments before a node and trailing comments after
it:
```rust
let node_comments = comments.leading_dangling_trailing(node_ref);
leading_comments(node_comments.leading).fmt(f)?;
self.fmt_fields(node, f)?;              // node-specific formatting
// debug_assert: all dangling comments must have been formatted manually by fmt_fields
write!(f, [ ... trailing_comments(node_comments.trailing) ])
```
Dangling comments are *not* auto-emitted; each node's `fmt_fields` must place them explicitly
(the `debug_assert` at `lib.rs:77-83` enforces this). The `leading_comments`/`trailing_comments`
builders live in `comments/format.rs`.

**Relevance to a new formatter:** the per-node (not per-token) association with a 3-way
leading/dangling/trailing classification is a pragmatic model that keeps comment handling
tractable. The cost is a large heuristics file (`placement.rs`). A CST would avoid heuristics but
complicates everything else. Ruff's choice: AST + heuristic re-attachment + a hard invariant that
every comment is printed exactly once.

---

## 2. Line-breaking algorithm / IR — Wadler/Prettier-style "Doc", called `FormatElement`

Yes. `ruff_formatter` is a Wadler/Prettier-style pretty-printer. **Lineage: it was forked from
Rome, which became Biome** (`biome_formatter`). The IR, the `group`/`indent`/`line` builder
vocabulary, the `Format`/`FormatRule` traits, and the fits-measurement printer are all the
Rome/Biome design. (This repo also has Biome checked out as a sibling at `repos/biome`.)

### Core IR type: `FormatElement`

`crates/ruff_formatter/src/format_element.rs:21`:
```rust
pub enum FormatElement {
    Space,
    Line(LineMode),          // SoftOrSpace | Soft | Hard | Empty
    ExpandParent,            // forces the enclosing group to expand  <-- key for magic comma
    SourcePosition(TextSize),
    Token { text: &'static str },
    Text { text: Box<str>, text_width: TextWidth },
    SourceCodeSlice { slice, text_width },   // verbatim source, zero-copy
    LineSuffixBoundary,
    Interned(Interned),      // shared sub-trees (for best_fitting / conditionals)
    BestFitting { variants, mode },          // printer picks the first variant that fits
    Tag(Tag),                // Start/End markers: Group, Indent, Align, LineSuffix, ...
}
```
`LineMode` (`:110`): `SoftOrSpace` (soft break or a space when flat), `Soft` (nothing when flat,
break when expanded), `Hard` (always breaks), `Empty` (blank line).

Builder vocabulary (`crates/ruff_formatter/src/builders.rs`): `space()`, `soft_line_break()`,
`hard_line_break()`, `soft_line_break_or_space()`, `empty_line()`, `text()`/`token()`,
`group(&content)` (`:1401`), `indent`, `dedent`, `align`, `if_group_breaks` /
`if_group_fits_on_line`, `indent_if_group_breaks`, `expand_parent()` (`:1777`), `best_fitting!`,
`fill` (Prettier "fill" for wrapping sequences). This is exactly the Prettier "Doc" builder set.

A `group` starts in `GroupMode::Flat` and the printer tries to print it flat; if it doesn't fit it
switches to `Expanded` (soft breaks become real line breaks). `GroupMode` (in
`format_element/tag.rs`): `Flat | Expand | Propagated`.

### Two-phase model: Document + printer

1. `Format` rules write `FormatElement`s into a buffer → a `Document` (a `Vec<FormatElement>`).
2. `Document::propagate_expand()` (`format_element/document.rs:33`) does a **single pre-pass** that
   marks groups as `Propagated` (must-expand) if they contain a hard/empty line, an `ExpandParent`,
   multi-line text, or an already-expanded nested group. `BestFitting` and best-fit-parenthesize
   act as **expansion boundaries** — expansion inside them does not leak to parents (`:29-30`,
   `:103-109`). This is what makes `expand_parent()` (and thus the magic trailing comma) work.
3. The **printer** (`crates/ruff_formatter/src/printer/mod.rs`) walks the document and decides,
   per group, flat vs expanded, by measuring whether it *fits* in the remaining line width.

### How "fits" is decided (width measurement)

The printer keeps a `line_width` and compares against `options.line_width` (default 88, Black's
default). When it hits a `StartGroup` in flat mode it runs a `FitsMeasurer`
(`printer/mod.rs:1044`) that speculatively measures the group's content:
- `fits()` (`:1099`) walks elements returning `Fits::{Yes,No,Maybe}` per element (`fits_element`
  `:1166`); returns `No` as soon as `line_width > options.line_width` (`fits_text` `:1485-1537`,
  `exceeds_width` `:1486`), `Yes` when a line break is reached and everything so far fit.
- `must_be_flat`: if a group is forced-flat but its mode isn't flat, it immediately fails
  (`fits_group` `:1464`).
- A group that was marked `Propagated`/`Expand` by the pre-pass is printed expanded without
  measuring.

`BestFitting` (`printer/mod.rs` handling around `:560-730`) tries each variant in order and prints
the first that fits — used for the "try flat, else parenthesize+indent" expression layouts.

**Relevance to a new formatter:** this is the proven, well-factored design to copy. The IR +
group/soft-line + fits-measurement gives you "collapse if it fits, else break" essentially for
free, and it composes. The Rome→Biome→Ruff lineage means three independent, mature
implementations exist to reference (Biome's is in `repos/biome/crates/biome_formatter`).

---

## 3. Formatting flow (passes)

```
source string
  └─(ruff_python_parser)→ AST (Parsed<Mod>) + token stream
  └─(TriviaRanges::from tokens)→ comment ranges + parenthesized ranges
        │
        ▼
  Comments::from_ast(ast, source, trivia)      # visitor + placement heuristics
        │  (leading/dangling/trailing per node)
        ▼
  Format rules (FormatNodeRule per node type)  # write FormatElements into a buffer
        │   builders: group/indent/soft_line_break/expand_parent/…
        ▼
  Document (Vec<FormatElement>)
        │
  Document::propagate_expand()                 # mark must-expand groups (single pre-pass)
        │
        ▼
  Printer (fits measurement, flat vs expanded) # FormatElement IR → String
        │
        ▼
  Printed { code, source_map }
```

Node dispatch: `parsed.syntax().format()` uses the `AsFormat`/`FormatRule` machinery
(`shared_traits.rs`, generated impls in `generated.rs`). Each AST node type implements
`FormatNodeRule` (`lib.rs:52`); the per-node rules live under `src/statement/`, `src/expression/`,
`src/other/`, `src/pattern/`, `src/type_param/`, `src/module/`, `src/string/`.

Idempotency is a design invariant, tested extensively (re-formatting formatted output must be a
no-op). `formatted_file` (`lib.rs:180`) returns `None` when output == input, i.e. already formatted.

---

## 4. Idempotency & preservation — the MAGIC TRAILING COMMA (deep dive)

Ruff (like Black) is **mostly canonical** (one normalized form) but has a small set of
"user-choice-preserving" escape hatches. The magic trailing comma is the flagship example, and it
is exactly the "user choice preserved" behavior relevant to a multi-form formatter.

**Behavior:** a collection/call/params list normally *collapses* onto one line if it fits and
*expands* one-per-line if it doesn't. BUT if the user wrote a trailing comma after the last
element, Ruff keeps it **expanded regardless of whether it would fit**. Removing the trailing
comma lets it collapse again. So the trailing comma is a user-controlled toggle between two valid
layouts.

### Option

`MagicTrailingComma { Respect, Ignore }` — `crates/ruff_python_formatter/src/options.rs:313`,
default `Respect` (`:319`+, `:96`). CLI `--skip-magic-trailing-comma` maps to `Ignore`
(`cli.rs:44,65`). `Ignore` reproduces Black's `--skip-magic-trailing-comma`.

### Detection — re-scanning the ORIGINAL source

`crates/ruff_python_formatter/src/other/commas.rs`:
```rust
pub(crate) fn has_magic_trailing_comma(range: TextRange, context: &PyFormatContext) -> bool {
    match context.options().magic_trailing_comma() {
        MagicTrailingComma::Respect => has_trailing_comma(range, context),
        MagicTrailingComma::Ignore  => false,
    }
}
pub(crate) fn has_trailing_comma(range: TextRange, context: &PyFormatContext) -> bool {
    let first_token = SimpleTokenizer::new(context.source(), range)   // <-- tokenizes ORIGINAL source
        .skip_trivia()
        .find(|token| token.kind() != SimpleTokenKind::RParen);       // skip closing parens
    matches!(first_token, Some(SimpleToken { kind: SimpleTokenKind::Comma, .. }))
}
```
It re-tokenizes the source text in the range between the last element's end and the sequence's
closing delimiter, skips any closing `)` (so `(a,)` and `foo(a,)` work), and checks whether the
first meaningful token is a comma. **The AST alone does not tell you this** — the presence of a
trailing comma is a lexical/source fact, so Ruff goes back to the raw source. This is the crucial
pattern for a "preserve user choice" formatter.

### Flow through the IR — `join_comma_separated`

`crates/ruff_python_formatter/src/builders.rs:213-251` (`JoinCommaSeparatedBuilder::finish`):
```rust
if let Some(last_end) = self.entries.position() {
    let magic_trailing_comma = has_magic_trailing_comma(
        TextRange::new(last_end, self.sequence_end), self.fmt.context());

    // Emit a trailing comma that only shows when the group breaks:
    if magic_trailing_comma
        || self.trailing_comma == TrailingComma::OneOrMore
        || self.entries.is_more_than_one() {
        if_group_breaks(&token(",")).fmt(self.fmt)?;   // conditional trailing comma
    }

    // The magic bit: force the enclosing group to expand.
    if magic_trailing_comma {
        expand_parent().fmt(self.fmt)?;                // <-- FormatElement::ExpandParent
    }
}
```
Two IR pieces do the work:
1. `if_group_breaks(&token(","))` — emits the trailing comma **only in expanded mode** (so a
   collapsed collection has no trailing comma; an expanded one does — the normal Black rule).
2. `expand_parent()` — writes `FormatElement::ExpandParent` into the buffer. During
   `Document::propagate_expand()` (`document.rs:131-138`) this marks the enclosing `group` as
   must-expand (`Propagated`), so the printer skips the fits-check and prints it expanded. That in
   turn makes `if_group_breaks` emit the comma. Net effect: user's trailing comma → group stays
   expanded → trailing comma preserved. No trailing comma → group collapses if it fits.

Because `propagate_expand` treats `BestFitting`/best-fit-parenthesize as boundaries, a magic
comma inside a sub-collection expands *its* group but doesn't necessarily blow up unrelated
parents.

Callers of this machinery (each computes the right range and calls `has_magic_trailing_comma`):
- `other/arguments.rs:199` — call arguments `foo(a, b,)`.
- `other/parameters.rs:209-233` — function params (lambdas have special handling; a lambda's
  comma "behaves like a magic trailing comma, it's just preserved").
- `statement/stmt_with.rs:275,323` — parenthesized `with` items.
- List/set/dict/tuple literals and comprehensions go through `join_comma_separated`
  (`builders.rs`), which calls it in `finish()`.

**Single-element nuance** (`builders.rs:235-239`): for a single entry, the trailing comma is only
added if it was already present (magic) or `TrailingComma::OneOrMore` is set — you don't
*introduce* a trailing comma on a one-element collection just because it breaks. (`(a,)` tuples
need their comma for semantics; that's the `OneOrMore` case.)

**Relevance to a new formatter:** this is the exact template for "preserve a user-chosen layout".
The recipe is:
1. Detect the user-intent signal by re-scanning original source/tokens (not just the AST).
2. Represent both layouts in one IR via conditional elements (`if_group_breaks`).
3. Use a one-directional "force expand" signal (`ExpandParent` + a propagation pre-pass) to pin the
   choice, rather than duplicating layout logic.

---

## 5. Other places original formatting is preserved based on user input

Ruff is more Black-like (canonical) than "multiple valid forms", but several deliberate
preservation points exist — all following the same "peek at the original source" pattern:

1. **Blank lines between statements** — `statement/suite.rs:385-405`. The *number* of blank lines
   is preserved up to a cap that depends on nesting level: up to 2 at module top-level, up to 1
   inside a compound statement, 0 inside expressions. It reads `lines_after(end, source)` on the
   original source and clamps:
   ```rust
   NodeLevel::TopLevel(_) => match lines_after(end, source) {
       0 | 1 => hard_line_break(),          // collapse 0/1 blank → no blank
       2     => empty_line(),               // keep 1 blank
       _     => empty_line()+empty_line(),  // clamp 2+ blanks to 2
   }
   ```
   So the user's blank-line grouping is preserved within limits. (Stub `.pyi` files have bespoke
   rules, `suite.rs:565`+.)

2. **Redundant/optional parentheses** — `expression/parentheses.rs`. `Parenthesize::Optional`
   (`:57`) *"Parenthesizes the expression if it doesn't fit on a line OR if the expression is
   parenthesized in the source code."* The resolved `Parentheses::Preserve` (`:96`, the default)
   keeps source parentheses. Whether an expression is parenthesized in source is looked up from
   `trivia.parenthesized()` (the trivia crate records parenthesized ranges;
   `parentheses.rs:457`). So user-added grouping parens around e.g. a long boolean expression can
   be respected as a break point. `OptionalParentheses::{Multiline,Always,BestFit,Never}` (`:14`)
   govern when parens are *added*.

3. **Format suppression comments** (`fmt: off` / `fmt: on` / `fmt: skip`) — `verbatim.rs` +
   `statement/suite.rs:173-185,408-427`. Regions marked suppressed are emitted **verbatim** from
   the original source (`write_suppressed_statements_*`, `write_skipped_statements`) using
   `FormatElement::SourceCodeSlice`. Detected via `is_suppression_off_comment` /
   `SuppressionKind` (`ruff_python_trivia`). This is total preservation of user formatting for the
   suppressed span. `has_skip_comment` handles `# fmt: skip` at statement level.

4. **String quote preference / prefixes** — `src/string/`, options `QuoteStyle`,
   `NestedStringQuoteStyle` (`options.rs`). Ruff normalizes quotes but has rules that avoid
   churn/escaping; docstring code formatting is opt-in (`DocstringCode`). (Less "user choice
   preserved", more "avoid gratuitous changes".)

5. **Range formatting** — `src/range.rs` (`format_range`) formats only a selected range and
   leaves the rest untouched — relevant if a new tool wants to reformat only out-of-spec regions.

---

## Key takeaways for building a NEW multi-form formatter

- **AST + trivia + original source, not a CST.** Format from the AST, but keep the source string
  and token stream in the format context and re-scan them for lexical decisions the AST drops
  (trailing commas, source parentheses, blank-line counts). This is the single most important
  enabler of "preserve user choice."
- **Adopt the Prettier/Wadler Doc IR** (`FormatElement`: group / soft_line_break /
  hard_line_break / indent / if_group_breaks / fill / best_fitting) with a fits-measurement
  printer. Ruff's `ruff_formatter` (and its ancestor Biome `biome_formatter`) are copy-worthy,
  mature references. It gives "collapse if fits, else break" compositionally.
- **The magic-trailing-comma pattern is the reusable recipe for multiple valid forms:**
  (a) detect the user signal from source; (b) encode both layouts once via `if_group_breaks`;
  (c) pin the choice with a one-way `ExpandParent` signal resolved in a `propagate_expand`
  pre-pass. To *allow* multiple forms rather than normalize, you generalize this: instead of one
  canonical layout with a few preserved toggles, let more constructs carry a "respect source"
  signal.
- **Comment handling is the hard part.** Per-node leading/dangling/trailing with heuristic
  re-attachment (`placement.rs`, 2462 lines) and a hard "every comment printed exactly once"
  invariant. Budget for this.
- **Idempotency must be an explicit, tested invariant.** Ruff checks output==input to detect
  already-formatted files and has extensive re-format-is-noop tests.
- **Suppression + range formatting** give escape hatches: verbatim `SourceCodeSlice` passthrough
  for `# fmt: off/skip`, and range-limited formatting — both directly useful for "only reformat
  out-of-spec code."
```
