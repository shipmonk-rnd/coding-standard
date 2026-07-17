# mago (carthage-software/mago) — architecture study

Repo: `/p/shipmonk/+tasks/oss-coding-standard-from-scratch/repos/mago`
Version studied: 1.43.0 (`Cargo.toml:4`), edition 2024, Rust workspace of ~30 crates.

A comprehensive PHP toolchain in Rust (parser, linter, formatter, analyzer/type-checker).
**This is the single most relevant reference for us: it targets PHP, and its formatter is a
faithful port of Prettier's Doc-IR algorithm.**

Relevant crates:
- `crates/syntax` — PHP lexer + parser + AST (they call it "cst").
- `crates/syntax-core` — shared lexing/parsing primitives (`Input`, `Sequence`).
- `crates/span` — `Span`/`Position` (byte offsets).
- `crates/formatter` — the Prettier-style formatter (Doc IR + printer).

---

## 6. License (checked first — matters for borrowing)

**Dual `MIT OR Apache-2.0`** (`Cargo.toml:5`, files `LICENSE-MIT`, `LICENSE-APACHE`).
Copyright Saif Eddin Gmati / Carthage Software. Permissive — we may reuse/borrow code
or ideas freely (attribution + license text). No copyleft obstacle.

---

## 1. PHP parser & data model

### Where
- Lexer: `crates/syntax/src/lexer/` (entry `lexer/mod.rs`, modes `lexer/internal/mode.rs`).
- Parser: `crates/syntax/src/parser/` (entry `parser/mod.rs`, `Parser::new`).
- AST: `crates/syntax/src/cst/` — one file per construct under `cst/cst/`.
- Public API: `parse_file_with_settings(arena, file, settings) -> &Program` (`syntax/src/parser/mod.rs`).

### It is an AST + separate trivia buffer — NOT a lossless CST
Despite the directory name `cst`, this is a **plain abstract syntax tree with rich spans**, not a
lossless green/red tree (nothing like rowan). Key evidence — `Program` (`syntax/src/cst/mod.rs:27`):

```rust
pub struct Program<'arena> {
    pub file_id: FileId,
    pub source_text: &'arena [u8],   // original bytes kept for source recovery
    pub trivia: Sequence<'arena, Trivia<'arena>>,   // ALL whitespace+comments live HERE
    pub statements: Sequence<'arena, Statement<'arena>>,
    pub errors: &'arena [ParseError],
}
```

- **Trivia (whitespace + comments) is stored in a single flat `Program.trivia` sequence, off to the
  side of the tree** — the statement nodes do NOT own their surrounding whitespace/comments.
- AST nodes DO keep every *syntactic* token as a typed field with a `Span` (e.g. `Array.left_bracket`,
  `Array.right_bracket`, keyword tokens). So the tree is "full-fidelity for tokens" but relies on
  `source_text` + spans + the trivia buffer to reconstruct the original exactly.
- Everything is **arena-allocated** (`'arena` lifetime, `mago_allocator::Arena`), which is why nodes
  are `&'arena` references and cheap to build; no `Rc`/`Box` graph.

### Trivia representation (`syntax/src/cst/trivia.rs`)
```rust
pub enum TriviaKind { WhiteSpace, SingleLineComment, MultiLineComment, HashComment, DocBlockComment }
pub struct Trivia<'arena> { pub kind: TriviaKind, pub span: Span, pub value: &'arena [u8] }
```
Whitespace is preserved as trivia too (`TriviaKind::WhiteSpace`), so the raw layout is recoverable
even though the parse tree ignores it. Comments are queried via `.comments()` extension iterator
(`trivia.rs:82`).

### Lexer is a stateful mode machine (the standard PHP approach)
`LexerMode` (`syntax/src/lexer/internal/mode.rs:35`): `Inline` (outside `<?php>`, emits `InlineText`),
`Script`, `DoubleQuoteString`, `ShellExecuteString`, `DocumentString(Heredoc|Nowdoc, label, indent,
interpolation)`, `Halt` (after `__halt_compiler`). This mode stack is how mixed HTML/PHP, string
interpolation, and heredoc/nowdoc are handled cleanly (see §5).

### Parser
Recursive-descent, hand-written, arena-based, with `MAX_RECURSION_DEPTH = 512`
(`parser/mod.rs:20`) to avoid stack overflow. **Error-tolerant**: errors are collected into
`Program.errors` rather than aborting, and there are `Missing` node variants (e.g.
`ArrayElement::Missing`, seen in `array.rs:56`) so a partial tree is still produced.

---

## 2. Formatter architecture — Prettier Doc IR

Yes: mago's formatter is a **direct port of Prettier's Wadler-style "Doc" algorithm**. Two layers:

### (a) The Doc IR — `crates/formatter/src/document/mod.rs`
```rust
pub enum Document<'arena, A> {
    String(&'arena [u8]),
    Array(Vec<Document>),
    Indent(Vec<Document>),            // +1 indent level
    IndentIfBreak(IndentIfBreak),     // indent only if a referenced group broke
    Group(Group),                     // the fit-or-break unit
    Line(Line),                       // line/softline/hardline/literalline (flags below)
    LineSuffix(Vec<Document>),        // buffered trailing content (trailing comments)
    LineSuffixBoundary,
    IfBreak(IfBreak),                 // break_contents vs flat_content (+ optional group_id)
    Fill(Fill),                       // Prettier "fill": break only separators that overflow
    BreakParent,                      // force all enclosing groups to break
    Align(Align),                     // align to a string prefix
    Trim(Trim), DoNotTrim,            // trailing-whitespace / newline trimming
    Space(Space),                     // hard or "soft" (collapsing) space
}
```
`Line` is a flag struct (`document/mod.rs:69`): `hard`, `soft`, `literal` — so `Line::default()` =
`line`, `Line::soft()` = `softline`, `Line::hard()` = `hardline`, `Line::literal()` = `literalline`.
Helper `Document::join(.., Separator)` builds comma/line separated lists
(`document/mod.rs:316`, `Separator` enum at `:133`).

**Notable addition over Prettier: `Group.break_mode: BreakMode` (`document/mod.rs:85`):**
```rust
pub enum BreakMode { Auto, Force, Preserve }
```
- `Auto` — normal fit-or-break.
- `Force` — always break, and **propagates** to parent groups.
- `Preserve` — always break, but does **NOT** propagate to parents.
This `Preserve` mode is the mechanism behind "keep it multiline because the user wrote it multiline"
without forcing everything around it to also break (see §4).

### (b) The printer / break algorithm — `crates/formatter/src/internal/printer/mod.rs`
Standard Prettier machine: a work stack of `Command{indentation, mode, document}` with
`Mode::{Flat, Break}` (`printer/mod.rs:90` `print_doc_to_string`). For each `Group`
(`handle_group`, `:204`): try it in `Flat` mode, call `fits()` (`:493`) which measures whether the
group + trailing content stays within `print_width`; if it fits, keep flat, otherwise re-emit the
group's contents in `Break` mode. `conditionalGroup`/`expanded_states` are supported (tries each
candidate layout until one fits). `propagate_breaks` (`:593`) walks the doc once to turn groups
containing `BreakParent`/forced children into `Force` — respecting `Preserve` (line 624: a `Preserve`
group is not upgraded to `Force`, i.e. it stays local). `fits()` treats a flat-mode `Preserve` group
as an immediate non-fit (`:530`) so preserved groups always break.

`Fill`, `LineSuffix` (trailing comments buffered until the next newline), `Trim`, tabs-vs-spaces, and
configurable EOL are all handled here. This is essentially a 1:1 reimplementation of Prettier's
`doc/printer.js`.

---

## 3. Formatting flow

`crates/formatter/src/lib.rs`:
1. `Formatter::format_file` → `parse_file_with_settings(arena, file, parser_settings)` → `&Program`
   (AST). Parse errors abort with the first `ParseError` (`lib.rs:108`).
2. `build()` (`lib.rs:134`): if any comment contains `@mago-format-ignore` / `@mago-formatter-ignore`
   the *entire file* is returned verbatim (`has_format_ignore_comment`, `:165`). Otherwise
   `program.format(&mut FormatterState)` walks the AST and produces one `Document`.
3. `FormatterState::new` (`formatter/src/internal/mod.rs:150`) runs **`place_comments(...)` once up
   front** to attach every trivia comment to a node as leading/trailing/dangling (Prettier-style),
   plus builds "ignore regions" from `@mago-*-ignore` markers.
4. `print()` (`lib.rs:157`): `Printer::new(...).build()` renders the Doc to `&[u8]`.

So the pipeline is exactly: **bytes → lexer(mode machine) → recursive-descent parser → arena AST
(+ separate trivia) → comment placement pass → Doc IR (per-node `format`) → Prettier printer → bytes.**

### Comment attachment — `formatter/src/internal/comment/`
Comments are NOT in the tree; they are matched to nodes at format time.
`placement.rs` computes, for each comment, an `enclosing` node and whether it's `Leading`/`Trailing`
and `OwnLine`/`EndOfLine` (`CommentLinePosition`, `placement.rs:12`). Result stored in a
`Comments` map keyed by node `Span` (`placement.rs:58`). During `format`, each node emits its
attached leading/trailing/dangling comments (`f.print_trailing_comments(span)`,
`f.print_dangling_comments(span, ..)` — see `array.rs:128,149,237`). Trailing comments use the
`LineSuffix` doc so they don't collide with following code.

---

## 4. Preservation of user formatting — THE key section for us

**Yes. This is exactly the behaviour we want, and mago implements it well.** mago does NOT use
Prettier's "magic trailing comma" trigger. Instead it uses a **"was there a newline right after the
opening delimiter?"** heuristic, gated by a family of opt-in settings, and realized with the
`BreakMode::Preserve` group mode.

### The general mechanism
For each collection-like construct, mago checks `has_new_line_in_range(source_text, <open delim end>,
<first element start>)`. If the user put a newline there, the group is created with
`BreakMode::Preserve` → it stays expanded even though it would fit on one line; if not, it's `Auto`
and collapses/expands purely by width. This means **both `[1, 2]` and the expanded multi-line form
are valid inputs and each is preserved** — precisely our core requirement.

### Arrays — `formatter/src/internal/format/array.rs:158`
```rust
let preserve_break = f.settings.preserve_breaking_array_like
    && misc::has_new_line_in_range(f.source_text, array_like.start_offset(), elements[0].start_offset());
...
Document::Group(Group::new(parts).with_id(group_id).with_break_mode(
    if force_break { BreakMode::Force }
    else if preserve_break { BreakMode::Preserve }   // <-- keep user's multiline
    else { BreakMode::Auto }))
```
`preserve_breaking_array_like` **defaults to `true`** (`settings.rs:478`). Trailing comma on break is
a separate `IfBreak` (`array.rs:233`, `f.settings.trailing_comma`, default true — `settings.rs:109`),
so on break a trailing comma is added and on flat it isn't.

### The full family of preserve-breaking settings (`formatter/src/settings.rs`)
Same `has_new_line_in_range` idiom is applied per construct, each behind its own flag:
- `preserve_breaking_array_like` — **default `true`** (`:478`) — arrays / `list()` / `array()`.
- `preserve_breaking_argument_list` — default `false` (`:444`) — call arguments
  (`call_arguments.rs:614`).
- `preserve_breaking_parameter_list` — default `false` (`:483`) (`parameters.rs:172`).
- `preserve_breaking_attribute_list` — default `false` (`:488`) — `#[Attr(...)]`.
- `preserve_breaking_member_access_chain` (+ `_first_method_on_same_line`) — default `false`
  (`:415`/`:439`) — `$o->a()->b()` chains (`member_access.rs:243`).
- `preserve_breaking_conditional_expression` — default `false` (`:493`) — ternaries.
- `preserve_breaking_condition_expression` — default `false` (`:501`) — `if/while/switch/match` heads.
- `preserve_breaking_binary_expression` — default `false` (`:514`) — newline before `&&`/`.`/`??`.

So out of the box only **arrays** preserve user line-breaks; the others are opt-in (they can be
enabled to coexist with PHP-CS-Fixer, per the doc comments). For our formatter this catalog is a
ready-made spec of *which* constructs benefit from "allow both forms".

### Related knobs
- Table-style array alignment (`array.rs:171` `is_table_style` / `calculate_column_widths`) — aligns
  arrays-of-arrays into columns.
- Key-value / assignment alignment runs (`array.rs:662`, `align_assignment_like`).
- `inline_single_element` (`array.rs:276`) — a single "expandable" element (closure/nested array/
  match/substantial call) is inlined rather than forced to break.

### Match arms (task called these out)
`format_match_arm` (`expression.rs:1132`). Match bodies always lay each arm on its own line (hard
lines), so match is not a "single-line-vs-multi-line preserve" case — arms are canonicalized. There
is no `preserve_breaking_match_arm`; the closest control is `preserve_breaking_condition_expression`
for the `match (...)` head. **Takeaway for us:** decide deliberately whether match arms are one of the
"multiple valid forms" constructs — mago chose to always expand them.

---

## 5. PHP-specific concerns

- **Mixed HTML/PHP (inline HTML, `?>...<?php`)**: handled at the lexer via `LexerMode::Inline` vs
  `Script` (`mode.rs:35`); inline HTML becomes `Statement::Inline` nodes (raw bytes). The formatter
  leaves inline HTML essentially verbatim and has special rules around the boundary: `<?php` opening
  tag placement (`opening_tag_on_own_line`), template detection `is_inline_php_template`
  (`statement.rs:475` — counts non-trailing closing tags to decide if the file is a template), and
  suppresses added blank lines around `ClosingTag`/`Inline`/`HaltCompiler`
  (`should_add_new_line_or_space_after_stmt`, `statement.rs:397`). **This is a genuinely hard area** —
  once you're in template mode you must not reflow HTML or move code across `?>`/`<?php` boundaries.
- **Heredoc / nowdoc**: lexed via `LexerMode::DocumentString(kind, label, indent, interpolation)`
  (`mode.rs:71`); the closing-marker indentation is measured up front and body-line indentation is
  surfaced as whitespace trivia. Formatting in `expression.rs:1609` (`DocumentKind::Heredoc` /
  `Nowdoc`), gated by `indent_heredoc` (`settings.rs:1238`, default true) which re-indents the body
  using `Align`/`literalLine`. Body content is otherwise preserved literally (interpolation not
  reflowed).
- **`literalLine`** (`Line::literal`, `document/mod.rs:161`) exists precisely so heredoc/string
  bodies can emit newlines that reset to column 0 without applying normal indentation — important for
  any construct whose interior must stay byte-exact.
- **Attributes** `#[...]`: own construct (`cst/cst/attribute.rs`) with
  `preserve_breaking_attribute_list`.
- **`__halt_compiler`**: dedicated lexer `Halt` mode consuming the rest of the file as raw bytes.
- **String interpolation**: separate lexer modes with `Interpolation::{None,Until,BraceUntil}` — the
  `"$x"` / `"{$x->y}"` cases are tokenized, not regex-hacked.
- **Docblocks**: `TriviaKind::DocBlockComment` + a dedicated `phpdoc-syntax` crate and
  `syntax/src/comments/docblock.rs` for parsing/formatting PHPDoc.
- **Parenthesization**: `formatter/src/internal/parens.rs` decides where parens are needed
  (precedence), so the formatter can drop/add them safely.

---

## Assessment: reuse mago, or just learn from it?

**Both are viable; leaning toward "learn heavily / borrow selectively".**

Reasons it's attractive:
- **License is permissive (MIT/Apache-2.0)** — no legal blocker to vendoring the parser or formatter.
- It's a **mature, actively developed, full PHP parser** (arena-based, error-tolerant, handles PHP 8.x
  syntax, heredoc, attributes, enums, interpolation) — writing this from scratch is a large effort.
- Its formatter **already implements exactly our headline feature**: multiple valid forms with
  preservation via the `has_new_line_in_range` + `BreakMode::Preserve` pattern, per-construct and
  configurable. The catalog of `preserve_breaking_*` settings is effectively a design doc for us.
- The Prettier Doc-IR + printer is clean and directly reusable as the layout engine.

Caveats / friction:
- **It's Rust.** If our new formatter is Rust, mago's `syntax` + `formatter` crates could be depended
  on or forked directly. If it's PHP/TS, we can only port the algorithm, not the code.
- Deeply **arena-lifetime-threaded** (`'arena` everywhere) and tied to sibling crates
  (`mago_database`, `mago_span`, `mago_allocator`, `mago_php_version`) — pulling out just the parser
  or just the formatter means dragging several crates or doing surgery.
- mago is opinionated toward a canonical style (its own "PER"/preset defaults, `presets.rs`); our goal
  ("only reformat out-of-spec code, allow multiple forms") is a philosophical superset — mago
  *supports* it via flags but defaults to canonicalizing most constructs. We'd flip more of the
  `preserve_breaking_*` defaults to `true` and possibly add match-arm/other preservation.

**Concrete recommendation:** if we build in Rust, strongly consider depending on / forking
`mago_syntax` for parsing (saves months) and reimplementing (or reusing) the Doc-IR formatter with our
own default settings that favor preservation. If we build in another language, port two things
specifically: (1) the Doc IR + printer with the `BreakMode::Preserve` extension, and (2) the
`has_new_line_in_range`-at-opening-delimiter preservation heuristic applied per construct.
