# Oxc Formatter (`oxfmt`) — Architecture Notes

Research target: `crates/oxc_formatter` (JS/TS layer) + `crates/oxc_formatter_core`
(language-agnostic IR + printer). All paths below are relative to
`/p/shipmonk/+tasks/oss-coding-standard-from-scratch/repos/oxc`.

**One-line summary:** Oxc's formatter is a **Biome port** using a **Wadler/Prettier-style
"Doc" IR** (`FormatElement`) built from an **AST** (not a CST). It **fully re-prints to one
canonical form** — with a handful of deliberate, narrowly-scoped exceptions where it keys off the
*original source* (object expansion, blank-line preservation, template literals). Comments are a
**side array**, attached to nodes on-the-spot by source position.

---

## 0. Crate layout & provenance

- `crates/oxc_formatter` — JS/TS-specific: AST walking, comments, quotes, parentheses.
- `crates/oxc_formatter_core` — language-agnostic **IR + Printer + builders + macros**, ported
  from Biome's `biome_formatter`. Also used by `oxc_formatter_json`, css, graphql.
- Both `AGENTS.md` files state explicitly: *"ported from Biome"*, *"turns a parsed AST into an IR
  ('Document') of `FormatElement`s, then prints that IR via the shared Printer."*

---

## 1. Data model: AST + side-array comments (NOT a CST)

Oxc formats from a **plain AST**, not a CST/lossless tree. This is a notable divergence from Biome
(which works on a CST/`biome_rowan` green tree with trivia attached to tokens).
`crates/oxc_formatter/AGENTS.md` calls this out: *"Biome works on a CST rather than an AST, so its
code and strategy differ in detail."*

- Entry point parses with `oxc_parser` and hands the formatter `&Program` + `&[Comment]`:
  `crates/oxc_formatter/src/lib.rs:79` (`format`), `:174` (`format_program`),
  `:238` (`format_node` — builds `JsFormatContext::new(source_text, source_type, comments, ...)`).
- `preserve_parens: false` is **required** — the AST carries no parenthesis nodes; the formatter
  re-derives needed parens itself (`lib.rs:212`, and `src/parentheses/`).
- **Trivia (comments)** are NOT nodes and NOT attached to the tree. They live in a flat
  `&[Comment]` slice (from `program.comments`) and are consumed by source position via a `Comments`
  cursor: `crates/oxc_formatter/src/formatter/comments.rs`, `.../trivia.rs`.
  - AGENTS.md: *"Prettier pre-classifies comments per context, whereas oxc_formatter decides on the
    spot."* Comments are matched to leading/trailing/dangling positions **during** IR construction
    by comparing byte spans (e.g. `comments_in_range`, `comments_before`, `first_unprinted_span`).
- **Whitespace** is entirely discarded and re-synthesized — except the two source-keyed signals in
  §4 (a newline right after `{`, and blank-line counts between statements), which are recovered by
  re-scanning the original `source_text` on demand (`src/source_text.rs`).

Implication for a new formatter: oxc proves you can get Prettier-grade output from a *lossy* AST as
long as you keep the original `source_text` around and re-scan it for the few decisions that need
original layout. You do **not** need a full CST.

---

## 2. Line-breaking: a Wadler/Prettier "Doc" algebra (`FormatElement`)

Yes — this is a classic **group/indent/line/softline/hardline** Doc IR, essentially Prettier's
`doc` builders re-typed in Rust (via Biome).

**Core IR type:** `enum FormatElement` — `crates/oxc_formatter_core/src/format_element/mod.rs:61`.
Variants that matter:

```rust
FormatElement::Space
FormatElement::Line(LineMode)          // SoftOrSpace | Soft | Hard | Empty | Literal   (:128)
FormatElement::ExpandParent            // force enclosing group to Expanded
FormatElement::Token { text }          // static ASCII, no breaks
FormatElement::Text  { text, width }   // arbitrary text; width precomputed (unicode-aware)
FormatElement::Interned(..)            // shared sub-doc (avoid deep clone)
FormatElement::BestFitting(..)         // printer picks the widest variant that fits  (:325)
FormatElement::Tag(Tag)                // Start/End of Group, Indent, Align, Fill, LineSuffix, ...
```

- `LineMode` (`:128`) is the softline/hardline/space distinction. `SoftOrSpace` = Prettier `line`,
  `Soft` = `softline`, `Hard` = `hardline`, `Empty` = blank line, `Literal` = verbatim newline.
- **Builders** (`crates/oxc_formatter_core/src/builders.rs`): `group`, `soft_block_indent`,
  `soft_line_break`, `soft_line_break_or_space`, `hard_line_break`, `indent`, `align`, `fill`,
  `line_suffix`, `if_group_breaks` / `if_group_fits_on_line`, `best_fitting!`. Doc is assembled
  with the `write!` / `format_args!` macros (`crates/oxc_formatter/src/lib.rs:41`), exactly
  mirroring Prettier's builder style.
- **Group tag** carries a mode and optional `GroupId` and a `should_expand` flag
  (`format_element/tag.rs`), so a group can be *forced* expanded independent of width.

**How a group decides to break (width measurement):**
`crates/oxc_formatter_core/src/printer/mod.rs:226` (`StartGroup`): the printer tries the group in
`PrintMode::Flat`, then calls `self.fits(...)` (`printer/mod.rs:380`, `FitsMeasurer` at `:946`) to
measure whether the flat rendering stays within `line_width`. If it fits → print `Flat`; otherwise
→ `Expanded` (soft lines become real newlines). This is the standard Prettier/Wadler
"try-flat-else-break" with a bounded look-ahead measurer. Width is accumulated in
`state.line_width` and compared against `PrinterOptions::line_width` (`printer/printer_options/`).
Text widths are precomputed and unicode/emoji-aware (`TextWidth`, `format_element/mod.rs:440`).

**Two-pass on the Doc before printing:** after the whole Doc is built, `Document::propagate_expand()`
(`format_element/document.rs:65`, called from `crates/oxc_formatter/src/formatter/mod.rs:90`)
walks the tree and marks any group containing a hard break / `ExpandParent` / multiline text as
forced-Expanded — Prettier's `propagateBreaks`. `will_break()` (`format_element/mod.rs:276`)
is the per-element predicate driving it.

---

## 3. Formatting flow (AST → IR → string)

`crates/oxc_formatter/src/formatter/mod.rs:65` (`format`) and `lib.rs`:

1. **Parse**: `oxc_parser` → `Program` AST + `comments: &[Comment]` (`lib.rs:221`).
2. **Build IR**: walk the AST, emitting `FormatElement`s into a flat arena `Vec` via `write!`.
   Each AST node has a `Format` impl under `crates/oxc_formatter/src/print/*.rs`
   (one file per construct: `array_expression.rs`, `object_like.rs`, `function.rs`, …).
   Comments/parens are resolved here on-the-spot.
3. **`Document::propagate_expand()`** — propagate forced breaks up through groups
   (`formatter/mod.rs:90`).
4. **Print**: `Printer` (`oxc_formatter_core/src/printer/mod.rs`) consumes the Document +
   `PrinterOptions` and emits the string, deciding flat vs expanded per group via `fits`,
   handling indentation, line suffixes (trailing line comments), and blank lines.
   Entry: `Formatted::print()` (`oxc_formatter_core/src/formatted.rs:39`).

There is also an optional **IR transform** stage between build and print for import sorting
(`crates/oxc_formatter/src/ir_transform/sort_imports/`) and Tailwind class sorting (collected during
build, sorted in one batch at print). Embedded languages (css/graphql-in-JS template literals)
build a child Doc inside the parent Doc (`external_formatter.rs`, `print/template/embed/`).

---

## 4. Idempotency & preservation — MOSTLY canonical, with a few source-keyed escapes

Oxc **re-prints to a single canonical form** (Prettier's philosophy: one output, idempotent). It
does **not** preserve arbitrary user line breaks. But there are a small, explicit set of decisions
that read the **original source layout** — these are the mechanisms most relevant to a
"multiple-allowed-forms" formatter:

### 4a. Objects: preserve the "expanded" shape if there's a newline after `{`  ← the key one
`crates/oxc_formatter/src/print/object_like.rs:97`:

```rust
let should_expand =
    f.options().expand == Expand::Auto && self.members_have_leading_newline(f);
...
write!(f, [group(&inner).should_expand(should_expand)]);
```

`members_have_leading_newline` (`object_like.rs:58`) checks the **original source**:
`f.source_text().contains_newline_between(o.span.start, first_property.span.start)`.
So `{ a }` stays collapsed but `{\n a }` is forced multi-line — Prettier's `objectWrap`. This is a
genuine *"the user put a newline here, so keep it expanded"* rule, and it is **the** place where
one construct legitimately has **two allowed forms** (collapsed or expanded) chosen from source.
It is gated by the `Expand` option (`src/options.rs:629`): `Auto` = preserve (default),
`Never` = always collapse when it fits.

### 4b. Arrays: NOT source-preserving — purely structural heuristic
`crates/oxc_formatter/src/print/array_expression.rs:46,66`: an array expands only if
`should_break()` is true, i.e. ≥2 elements that are *all* objects (≥2 props each) or *all* arrays
(≥2 elems each) — Prettier's "table" heuristic — or if there's a trailing line comment. A user
newline inside an array is **ignored**. (Contrast with objects — same as Prettier.)

### 4c. Blank lines between statements/members: collapse runs to at most ONE, but preserve one
`crates/oxc_formatter/src/formatter/builders.rs:116` (`JoinNodesBuilder::separator_no_entry`):

```rust
if self.has_lines_before(span) {      // source had > 1 line before this node
    write!(self.fmt, empty_line());   // emit exactly one blank line
} else {
    self.separator.fmt(self.fmt);     // normal hard break
}
```

`has_lines_before` → `lines_before(span) > 1` → `SourceTextExt::get_lines_before`
(`src/source_text.rs:83`), which counts source newlines (skipping ASI `;`, comments, and
non-preserved parens). So the *presence* of a blank line is preserved, but the *count* is
normalized to one. Trailing blank lines at file end handled in `print/program.rs:203,232`.

### 4d. Template literals & `prettier-ignore` / `oxfmt-ignore`
Template-literal contents and suppressed (`prettier-ignore`) ranges are copied **verbatim** from
source (`print/mod.rs` `suppressed_statement_content_end`, `spec/suppression.rs`), which is another
form of "preserve original". The `Literal` line mode and `text(..).without_expand_parent()`
(`format_element/mod.rs:540`) exist to emit verbatim multi-line content without disturbing groups.

**Everything else** (indentation, quotes, semicolons, spacing, wrapping of calls / args / chains /
binary expressions / JSX) is fully normalized and idempotent.

---

## 5. Prettier heritage

- Explicit goal: **Prettier output compatibility**; the *only* test suite is Prettier conformance
  (`cargo run -p oxc_prettier_conformance`, snapshots in `tasks/prettier_conformance/`). No unit
  tests for formatting per AGENTS.md.
- The IR is a faithful port of Prettier's Doc algebra (via Biome): `group`, `indent`, `line`/
  `softline`/`hardline`, `fill`, `ifBreak`, `lineSuffix`, `breakParent`, `propagateBreaks`, and
  `conditionalGroup` (= `BestFitting`). `format_element/mod.rs` and `builders.rs` name-check
  Prettier's `printDocToString`, `literallineWithoutBreakParent`, etc.
- Implementation strategy differs from Prettier where the AST/on-the-spot-comments approach forces
  it (see `crates/oxc_formatter/AGENTS.md` "Comment placement invariants" — a large hand-maintained
  compat table of measured Prettier behavior).

---

## 6. Relevance to a NEW formatter allowing MULTIPLE valid forms

The task's goal is a formatter that **accepts several valid layouts and only reformats out-of-spec
code**, rather than forcing one canonical form. Oxc/Prettier are the *opposite* philosophy (one
canonical form), but the pieces that could support "multiple allowed forms" are:

1. **Source-keyed expansion switch (the reusable pattern).** `object_like.rs:97` +
   `contains_newline_between` is the exact primitive: *look at the original source, and if the
   author chose a particular shape, keep it.* A multi-form formatter would generalize this from
   "objects only" to arrays, call arguments, params, imports, unions, etc. — i.e. make
   `should_expand = author_broke_it_here` the rule everywhere, not just for `{`. The
   `group(...).should_expand(flag)` API (`format_element/tag.rs`) already lets any group be pinned
   open regardless of width, so the IR fully supports "this group is allowed to be either form."
   `Expand::Auto` vs `Expand::Never` (`options.rs:629`) shows the intended toggle shape.

2. **`BestFitting` / `best_fitting!`** (`format_element/mod.rs:325`): the printer already models
   "here are N acceptable renderings, pick one." Today it picks the widest-that-fits, but it is
   structurally a *set of allowed forms* for one node — a natural hook for "any of these is fine,
   leave it if it already matches one."

3. **Blank-line preservation** (`builders.rs:116`, `get_lines_before`): a working example of
   *preserve author intent but normalize the amount* — the template for "allowed range of forms,
   clamp to spec" rather than "force exact."

4. **The lossy-AST + re-scan-source design** (§1) is the enabler: because oxc keeps `source_text`
   and re-reads it for layout decisions, adding more "was it broken in the source?" predicates is
   cheap and localized (`src/source_text.rs` `SourceTextExt`). A conformance-style formatter that
   only rewrites out-of-spec code would lean heavily on this pattern — compare the emitted Doc's
   allowed forms against what the source already is, and skip rewriting when it already conforms.

**Caveat / gap:** oxc has no notion of "the current formatting is one of several acceptable and
therefore leave it untouched" beyond the object/blank-line cases and `prettier-ignore`. Its
printer always *produces* output; it never *diffs against source to decide whether to act*. A
multi-form/idempotent-only-when-nonconforming formatter would need a new layer on top: build the
"set of allowed Docs" per node (using `BestFitting`-like variants + `should_expand`) and only emit
a change when the source matches none of them. The IR primitives above make that layer feasible,
but it does not exist in oxc today.
