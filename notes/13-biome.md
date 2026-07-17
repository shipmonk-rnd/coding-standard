# Biome formatter architecture (biomejs/biome, formerly Rome)

Rust JS/TS/JSON/CSS toolchain. Repo studied at commit `72d309b` (2026-07-17).
Relevant crates:
- `crates/biome_rowan` — lossless CST library (Rust port of rust-analyzer's `rowan` red-green trees, itself modeled on Roslyn).
- `crates/biome_formatter` — language-agnostic IR engine (Wadler/Prettier-style `Doc`). **Ruff's Python formatter was forked from this crate.**
- `crates/biome_js_formatter` — JS/TS language bindings on top of the engine.

Reading focus for a NEW "only reformat out-of-spec code" formatter: the **lossless CST + trivia model** (section 1) and the **"expand based on original source"** logic (section 4) are the most directly relevant. Sections 2/3/5 give the surrounding mechanism.

---

## 1. Data model — lossless CST (`biome_rowan`), the most important part

Biome parses source into a **lossless concrete syntax tree**: every byte of the input (whitespace, comments, even parser-skipped garbage) is preserved and the exact original text can always be reconstructed. `crates/biome_rowan/src/lib.rs:1` — *"A generic library for lossless syntax trees."*

It uses the **red-green tree** design (rust-analyzer/Roslyn):

### Green tree — immutable, position-independent, structurally shared
- Green nodes/tokens are reference-counted, deduplicatable, and store **relative** lengths only, never absolute positions — so identical subtrees can be shared. `crates/biome_rowan/src/green.rs:9` (`GreenNode`, `GreenNodeData`, `Slot`).
- A green node stores just `kind` + `text_len` + child slots. `GreenNodeHead` = `{ kind, text_len }` (`crates/biome_rowan/src/green/node.rs:21-27`). Children are `Slot`s carrying a **relative** offset: `Slot::Node { rel_offset, node }`, `Slot::Token { rel_offset, token }`, or `Slot::Empty { rel_offset }` for a missing optional/error child (`green/node.rs:34-48`).
- Green tokens are leaves that own the **actual text bytes** plus their leading and trailing trivia descriptors. `GreenTokenHead = { kind, leading: GreenTrivia, trailing: GreenTrivia }` (`green/token.rs:15-22`); the token's full text (trivia included) is the byte slice (`green/token.rs:108-111`, `text()`), and `text_trimmed()` strips the leading/trailing trivia lengths (`green/token.rs:120-129`). Nodes are tiny: `GreenNode`, `GreenToken`, `GreenTrivia` are each 8 bytes (a `ThinArc` pointer) — `green.rs:43-46`.

### Trivia — how whitespace/comments attach to tokens
- **All whitespace and comments are trivia carried by the tokens themselves**, split into a token's *leading* and *trailing* trivia. There are no whitespace nodes in the tree.
- `GreenTrivia` is a list of `TriviaPiece` (`green/trivia.rs:60-71`, `122-127`). A `TriviaPiece = { kind, length }` (`crates/biome_rowan/src/syntax/trivia.rs:48-52`) where `TriviaPieceKind` ∈ `Newline | Whitespace | SingleLineComment | MultiLineComment | Skipped` (`syntax/trivia.rs:8-20`).
- Trivia identity is by **kind+length, not text** — `\r` and `\n` share one `Newline` piece of length 1; the concrete bytes live on the owning token, so no information is lost (`green/trivia.rs:60-66`, doc comment).
- Trivia lengths are excluded when computing a token's trimmed text: `leading_trailing_total_len()` (`green/token.rs:113-118`).

### Red tree — cheap, transient, positioned cursor over the green tree
- The "red" layer (`crates/biome_rowan/src/cursor.rs`) is a *zipper*: a `SyntaxNode`/`SyntaxToken` points to a green node **plus its parent red node**, giving absolute offsets and upward/downward navigation without mutating the green tree (`cursor.rs:1-10`).
- `NodeData` holds a `NodeKind` (`Root { green }` owning the green root, or `Child { green: WeakGreenElement, parent: Rc<NodeData> }`) plus a cached absolute `offset: TextSize` (`cursor.rs:80-96`). Red nodes are created/destroyed on demand during traversal (`cursor.rs:22-25`) — cheap and non-persistent. Child red nodes reference their green counterpart via an unsafe weak raw pointer, relying on the parent chain keeping it alive (`cursor.rs:36-42`, `98-110`).
- Absolute position comes from `offset()` (`cursor/node.rs:68-71`) and `text_range()` (`cursor/node.rs:87-90`).

**Why this matters for a "only change what's wrong" formatter:** the CST is a total, byte-exact representation of the input — you can always emit any subtree's *original* text verbatim, and you can diff "what the formatter wants" against "what the source has" at the granularity of a single token's trivia. This is the correct foundation for allowing multiple valid forms and only rewriting out-of-spec regions. Biome itself exploits this with `format_verbatim` (emits a node's original source unchanged, `crates/biome_js_formatter/src/verbatim.rs:26`) and with range formatting (section 5).

---

## 2. IR / line-breaking engine (`biome_formatter`) — yes, a Wadler/Prettier Doc builder

The engine builds a language-agnostic IR called `FormatElement` (Biome's `Doc`), then a printer picks line breaks. **Ruff's formatter was forked from this crate.**

### `FormatElement` enum — `crates/biome_formatter/src/format_element.rs:22-87`
Core variants:
- `Space` / `HardSpace`
- `Line(LineMode)` where `LineMode ∈ SoftOrSpace | Soft | Hard | Empty` (`format_element.rs:129-139`)
- `ExpandParent` — forces the enclosing group to expanded mode (`:29`)
- `Token { text: &'static str }` (ASCII, no breaks), `Text { text, text_width }` (arbitrary), `LocatedTokenText { slice, text_width }` (zero-copy slice of a `SyntaxToken`), plus `Mapped*` variants that carry a source position for source-map generation (`:37-71`)
- `LineSuffixBoundary` (`:73-75`)
- `Interned(Rc<[FormatElement]>)` — shared reusable IR (`:79`, `165-172`)
- `BestFitting(BestFittingVariants)` — printer picks the widest variant that still fits (`:83`, `398-463`)
- `Tag(Tag)` — start/end markers for grouping/indent/etc. (`:86`)

The struct is deliberately kept at **24 bytes** (static assert `format_element.rs:599`).

`Tag` (start/end pairs) — `crates/biome_formatter/src/format_element/tag.rs:12`:
`StartGroup(Group)`/`EndGroup`, `StartIndent`/`EndIndent`, `StartAlign(Align)`/`EndAlign`, `StartDedent`/`EndDedent`, `StartConditionalContent(Condition)`/`End…`, `StartIndentIfGroupBreaks(GroupId)`/`End…`, `StartFill`/`EndFill`, `StartEntry`/`EndEntry`, `StartLineSuffix`/`End…`, `StartVerbatim(VerbatimKind)`/`End…`, `StartLabelled(LabelId)`/`End…`, `StartEmbedded(TextRange)`/`End…`, `StartBestFittingEntry`/`End…`.

### Builders — `crates/biome_formatter/src/builders.rs`
- `group(&content)` (`:1917`) → emits `Tag(StartGroup(...))`…`EndGroup` (`:1957-1963`). A group prints **flat** if it fits, else **expanded**. `.should_expand(true)` forces `GroupMode::Expand` (`:1944-1955`) — this is the hook section 4 uses.
- `indent(&content)` (`:882`) → `StartIndent`…`EndIndent`.
- `soft_line_break()` → `Line(Soft)` (nothing when flat, newline when expanded) (`:68`); `soft_line_break_or_space()` → `Line(SoftOrSpace)` (space when flat, newline when expanded) (`:189`); `hard_line_break()` → `Line(Hard)` always breaks (`:100`); `empty_line()` → `Line(Empty)` a blank line (`:132`).
- `space()` → `Space` (`:710`); `line_suffix(&content)` defers content to the next line break (`:527`).

### Printer & `fits` — `crates/biome_formatter/src/printer/mod.rs`
- Entry `Printer::print` → `print_with_indent` loops popping IR elements and calling `print_element` (a big match on `FormatElement`) writing to an output buffer (`:49-78`).
- **Group mode decision** (flat vs expanded), on `StartGroup` (`:188`):
  - if the group is forced (`!group.mode().is_flat()`) → `Expanded`;
  - else if a parent already proved the content fits flat (`measured_group_fits`) → stay `Flat`;
  - else push the group as `Flat`, call `self.fits(...)`, and choose `Flat` if it fits else `Expanded`. The resolved mode is recorded by `GroupId` in `group_modes` for later conditional/indent-if-breaks lookups.
- **`fits` measurement**: `Printer::fits` (`:350`) spins up a `FitsMeasurer` shadow-printer that walks elements accumulating `state.line_width` and comparing against `options.print_width`. Per-element verdict is a `Fits` enum: `Yes | No | Maybe` (`:1555-1565`). The loop (`:1120`) short-circuits `Yes` (e.g. a hard break ends the line → rest is irrelevant) / `No` (over width), and keeps scanning on `Maybe`. Width in `fits_text` (`:1452`): unicode `char.width()`, tabs = indent width, a `\n` in text → `No` when it must stay flat, and `line_width > print_width` → `No` (`:1473`).
- **`BestFitting`** — `print_best_fitting` (`:499`) tries each variant flat (first that fits wins), falling back to the most-expanded printed in `Expanded`. First variant = most flat, last = most expanded (`format_element.rs:452-462`).
- **`fill`** — measures each item/separator **separately** (not as one group), so a list wraps element-by-element; `print_fill_entries` (`:589`) classifies each item/sep pair into a `FillPairLayout`.

---

## 3. Formatting flow: CST → IR → string

Pipeline (parser produces the CST; the formatter takes a `SyntaxNode` root):

**source → parse → CST → build `FormatElement` IR (`Document`) → print IR → `Printed` string**

Main stages in `crates/biome_formatter/src/lib.rs`:
1. `format_node` (`:1709`) → `format_node_with_source_map_generation` (`:1723`), the real pipeline:
   - `language.transform(root)` — optional CST pre-transform (`:1729`).
   - `language.create_context(...)` — builds the `FormatContext`, including the **comments store** (`:1773`; JS binds this to `Comments::from_node(...)`, `crates/biome_js_formatter/src/lib.rs:579`).
   - `write!(buffer, [FormatRefWithRule::new(&root, rule)])` — **drives the whole tree, collecting `FormatElement`s into a `VecBuffer`** (`:1774-1779`). This is the CST→IR step.
   - `Document::from(buffer.into_vec())` then `document.propagate_expand()` finalizes which groups must break (`:1781-1782`).
   - returns `Formatted { document, context }` (IR, not yet a string) (`:1793`).
2. `Formatted::print` (`:1153`) → `Printer::new(options).print(&document)` — the IR→string step — producing `Printed { code, range, sourcemap, verbatim_ranges }` (`:1209`), then strips trailing newlines.

**Node → rule dispatch** (`Format`/`FormatRule` traits, Rust orphan-rule workaround):
- `Format<Context>::fmt` is Biome's `Display`-for-IR (`lib.rs:1338`); `FormatRule<T>` is the external rule newtype (`lib.rs:1405`); `FormatRefWithRule` glues node+rule into `Format` (`lib.rs:1490`).
- Root rule `FormatJsSyntaxNode` calls `map_syntax_node!` (`crates/biome_js_formatter/src/cst.rs:7-16`), a generated giant `match` on `JsSyntaxKind` that casts the untyped node to its typed AST node and calls `.format()`.
- Per-node rules are generated — 304 `impl FormatRule` blocks in `crates/biome_js_formatter/src/generated.rs`; each forwards to `FormatNodeRule::fmt` (`crates/biome_js_formatter/src/lib.rs:357-464`), which does: suppression check → leading comments → `fmt_node` (parentheses + embedded ranges) → hand-written `fmt_fields` → dangling → trailing comments.

**Comments** are extracted from token trivia once, up front, into a `Comments` store keyed by node (`crates/biome_formatter/src/comments.rs:814` `Comments::from_node`; `comments/builder.rs:58` `CommentsBuilderVisitor::visit` walks `preorder_with_tokens`, classifying each comment as leading/dangling/trailing). Each node's rule then emits its attached comments while writing IR.

---

## 4. Preservation of user formatting based on the ORIGINAL source (most relevant)

Biome is mostly a canonicalizing formatter (like Prettier), but it has **two trivia-driven decisions that preserve the user's original layout** — this is exactly the "keep a valid form the user chose" behavior a multi-form formatter needs.

### 4a. Object/array "expand" (magic-trailing-comma-style) — expand if the source was multiline
The `Expand` option — `crates/biome_formatter/src/lib.rs:909-918`:
```rust
pub enum Expand {
    /// Objects are expanded when the first property has a leading newline. Arrays are always
    /// expanded if they are shorter than the line width.
    #[default] Auto,
    Always,  // always expand
    Never,   // never expand (if shorter than line width)
}
```
**Objects/TS object types preserve source multiline-ness.** The decision inspects whether the original source put a newline between `{` and the first member — `crates/biome_js_formatter/src/utils/object_like.rs:67-69`:
```rust
let should_expand = (f.options().expand() == Expand::Auto
    && self.members_have_leading_newline())
    || f.options().expand() == Expand::Always;
```
`members_have_leading_newline` reads the CST trivia (`object_like.rs:28-33`, → `SyntaxNode::has_leading_newline`), and the boolean is fed into the group: `write!(f, [group(inner).should_expand(should_expand)])` (`object_like.rs:92`). `.should_expand(true)` sets `GroupMode::Expand` (`builders.rs:1944-1955`), forcing every soft line break inside to render as a real newline. So: **user wrote it expanded → stays expanded; user wrote it on one line → collapses if it fits.**

Arrays differ: in `Auto` they use a **content heuristic** (`should_break`: ≥2 elements that are all arrays/objects with ≥2 members) rather than source trivia — `crates/biome_js_formatter/src/js/expressions/array_expression.rs:47-48` and `should_break` (`array_expression.rs:87-129`); only `Expand::Always` force-expands them.

### 4b. Blank-line preservation — collapse ≥1 source blank line to exactly one
Statement lists join entries with `JoinNodesBuilder`, consulting the original trivia. `crates/biome_formatter/src/builders.rs:2666-2679`:
```rust
pub fn entry<L: Language>(&mut self, node: &SyntaxNode<L>, content: &dyn Format<Context>) {
    ...
    if self.has_elements {
        if get_lines_before(node) > 1 {
            write!(self.fmt, [empty_line()])?;   // source had a blank line → keep ONE
        } else {
            self.separator.fmt(self.fmt)?;        // otherwise just the hardline separator
        }
    }
    ...
}
```
`get_lines_before(node)` counts source newlines in the node's leading trivia up to the first comment (`builders.rs:2711-2731`). `> 1` collapses any number of blank lines to a single `empty_line()`. Used by statement lists (`crates/biome_js_formatter/src/js/lists/statement_list.rs:11-22`) and array-element Fill layout (`array_element_list.rs:54-60`).

### 4c. General source-trivia newline helpers (reusable primitives)
- `get_lines_before` / `get_lines_before_token` — count leading newlines (`builders.rs:2711-2731`).
- `SyntaxNode::has_leading_newline` (`crates/biome_rowan/src/syntax/node.rs:751-755`) → `SyntaxToken::has_leading_newline` (`syntax/token.rs:524-529`) → any leading `TriviaPiece::is_newline()`.
- These are the exact "did the source have a newline here?" queries a new formatter would build its "is this form acceptable / already conforming?" checks on.

---

## 5. Incremental / partial reformatting

The lossless CST makes both subtree rewriting and range formatting natural.

### CST is immutable → edits return new trees with structural sharing
Because green nodes are immutable and shared, "mutation" produces a **new** tree that reuses all untouched subtrees; only the path from the edited node to the root is rebuilt.
- `SyntaxNode::splice_slots(range, replace_with)` and `replace_child` (`crates/biome_rowan/src/syntax/node.rs:452`, `:474`; cursor impl `crates/biome_rowan/src/cursor/node.rs:410`, `:429`) return a new node — `#[must_use]`, "syntax elements are immutable, the result of update methods must be propagated".
- `SyntaxNode::clone_subtree` (`syntax/node.rs:436`), `detach` (`:442`), and trivia rewriters `with_leading_trivia_pieces` / `with_trailing_trivia_pieces` (`syntax/node.rs:487-499`; token-level `syntax/token.rs:194-285`) allow rewriting just a token's whitespace/comments while keeping the rest byte-identical.
- `SyntaxRewriter` (`crates/biome_js_formatter/src/syntax_rewriter.rs`, exported from `biome_rowan`) provides visitor-style CST transforms.

So a "only fix what's wrong" formatter can: keep the whole CST, reformat one offending subtree to a string, and splice that subtree's text back — everything else is preserved verbatim.

### Range formatting — `format_range` (already implemented)
`crates/biome_formatter/src/lib.rs:1922`:
- Resolves the start/end tokens for the requested `TextRange`, trims whitespace off the edges (`:1945-2011`).
- Walks ancestors to find the smallest node(s) that are valid "range formatting roots" (`language.is_range_formatting_node`), then finds the **lowest common ancestor** — explicitly following Prettier's `findSiblingAncestors` algorithm (`:2013-2072`).
- Formats that common-root subtree via `format_sub_tree` (which computes the initial indent from leading trivia, `:2187`), producing a `Printed` **with a source map** (`SourceMarker { source, dest }`, `:1070-1075`).
- Uses the source map to slice out **only the bytes corresponding to the requested range** and returns `Printed { code: <slice>, range: Some(input_range), ... }` (`:2076-2174`). The caller replaces `input_range` in the original text with `code` — a true splice-back.

### Verbatim / suppression
`format_verbatim` emits a node's **original source text unchanged** (used for parse errors and `// biome-ignore` suppressions), reading the exact slice from the CST (`crates/biome_js_formatter/src/verbatim.rs:26`, `:136`). This is the built-in "leave this region exactly as the user wrote it" escape hatch and is close in spirit to a formatter that only rewrites non-conforming spans.

---

## Takeaways for a new multi-form formatter
- **Adopt a lossless CST with token-attached leading/trailing trivia** (whitespace + comments as `TriviaPiece` kind+length; text on the token). This is the enabling foundation: you can always reproduce the original and reason per-token about what the user did. (`biome_rowan`)
- **Immutable red-green trees give cheap, safe subtree rewriting** (edits return new shared trees); range formatting + source maps let you splice reformatted text back into only the changed span. (`syntax/node.rs`, `format_range`)
- **The "expand based on original source" pattern is the template for accepting multiple valid forms**: read the source trivia (`has_leading_newline`, `get_lines_before`) and let it drive layout (`group.should_expand(...)`, `empty_line()`), instead of forcing one canonical shape. (`utils/object_like.rs:67`, `builders.rs:2669`)
- The Wadler/Prettier `Doc` engine (group/indent/line/`fits`/best_fitting) is a proven, forkable line-breaker (Ruff reused it), but it is fundamentally *canonicalizing* — the source-preserving hooks (4a/4b) are bolted on per-node, not a first-class "keep if already valid" mode.
