# dprint — Architecture Study (core IR + TypeScript plugin)

Repos studied:
- Core: `/p/shipmonk/+tasks/oss-coding-standard-from-scratch/repos/dprint`
  (crate `dprint-core` at `crates/core`)
- TS plugin: `/p/shipmonk/+tasks/oss-coding-standard-from-scratch/repos/dprint-plugin-typescript`

Relevance flag for our project (a formatter that permits MULTIPLE valid forms and only
reformats out-of-spec code): the two most transferable ideas are (a) dprint's **conditional
IR** where layout is a runtime decision driven by look-ahead over resolved positions, and
(b) its **"expand if the user wrote it expanded"** rule (`force_use_new_lines`), which is
exactly a "preserve one of several valid forms" mechanism. Both are analyzed in depth below.

---

## 1. Core IR — "print items" instead of the Wadler Doc algebra

### 1.1 The IR node types

The IR is a **singly linked list of `PrintItem`** (not a tree of Docs). Defined in
`crates/core/src/formatting/print_items.rs`.

`PrintItem` enum — `print_items.rs:485`:
```rust
pub enum PrintItem {
  String(UnsafePrintLifetime<StringContainer>),   // literal text (width precomputed)
  Condition(UnsafePrintLifetime<Condition>),       // runtime layout choice
  Signal(Signal),                                  // newline/indent/group markers
  RcPath(PrintItemPath),                           // pointer to a sub-list (composition)
  Anchor(Anchor),                                  // fix up a resolved line number
  Info(Info),                                      // a "probe" you can later read back
  ConditionReevaluation(ConditionReevaluation),    // re-run a condition later
}
```

`Signal` — `print_items.rs:496` — the primitive whitespace/layout markers:
- `NewLine`, `Tab`, `SingleIndent`
- `PossibleNewLine` — a candidate break point if the line overflows
- `SpaceOrNewLine` — a space, or a break if it overflows (the classic "line" of Wadler)
- `ExpectNewLine` — force a newline unless one is already coming (comment handling)
- `StartIndent`/`FinishIndent`, `QueueStartIndent`
- `StartNewLineGroup`/`FinishNewLineGroup` — grouping with *lower precedence* break points
- `StartIgnoringIndent`/`FinishIgnoringIndent` (template literals etc.)
- `StartForceNoNewLines`/`FinishForceNoNewLines` — hard "keep on one line" region
- `SpaceIfNotTrailing`

`Info` variants — `print_items.rs:578` — probes whose value is filled in *while printing*:
`LineNumber`, `ColumnNumber`, `IsStartOfLine`, `IndentLevel`, `LineStartColumnNumber`,
`LineStartIndentLevel`. Each gets a unique id from a thread-local counter
(`LineNumber::new` → `thread_state::next_line_number_id()`, `print_items.rs:648`).

`Condition` — `print_items.rs:857`:
```rust
pub struct Condition {
  id: u32,
  is_stored: bool,          // keep its resolved value around for later lookups
  store_save_point: bool,   // enable reevaluation
  condition: ConditionResolver,          // Rc<dyn Fn(&mut ConditionResolverContext) -> Option<bool>>
  true_path: Option<PrintItemPath>,       // items to emit if true
  false_path: Option<PrintItemPath>,      // items to emit if false / unresolved
}
```
The resolver returns `Option<bool>`: `None` means "cannot decide yet — depends on info not
yet printed", which triggers the look-ahead machinery (§1.3).

### 1.2 How this differs from the Wadler / `group`·`indent`·`line` algebra (Prettier / Biome / Ruff)

- **Doc tree vs. instruction stream.** Prettier/Biome/`ruff_formatter` build an immutable
  **tree** of `Doc`/`FormatElement` (`group`, `indent`, `line`, `softline`, `ifBreak`, …) and
  a separate `Printer`/`fits` pass measures each group and picks flat-vs-broken. dprint instead
  emits a **flat mutable linked list** of primitive items and walks it once (with backtracking).
- **Grouping is implicit and value-driven, not a `group(...)` wrapper.** There is no
  `group` combinator that atomically decides break/flat for a whole subtree. Instead a `Condition`
  runs an arbitrary closure over the *live writer state* (current column, whether a probe already
  resolved to a different line, etc.) and chooses `true_path`/`false_path`. Layout choices are
  therefore **imperative predicates over resolved positions**, and can even depend on information
  that appears *later* in the file — `print_items.rs:854`: "these conditions ... can even be
  resolved based on information found later on in the file."
- **`ifBreak` generalized.** Prettier's `ifBreak(a,b)` only asks "did my enclosing group break?".
  dprint's `Condition` can ask *anything*: "is this the start of a line?", "is line X below line
  Y?", "did condition Z resolve true?", "am I above width W?". Helper predicates live in
  `condition_helpers.rs` (`is_multiple_lines`, `is_hanging`, `is_on_different_line`, …) and
  `condition_resolvers.rs` (`is_start_of_line`, `is_start_of_line_indented`, …).
- **Explicit reevaluation + infinite-loop protection.** Because conditions can flip when a
  look-ahead resolves, dprint has `ConditionReevaluation` and an
  `InfiniteReevaluationProtector` (`printer.rs:519`, `infinite_reevaluation_protection.rs`) —
  a concern that doesn't exist in the pure functional `fits` model.
- **Arena / linked list for speed.** Items are bump-allocated (`thread_state::with_bump_allocator`,
  `print_items.rs:34`) and referenced by a faked `'static` lifetime (`UnsafePrintLifetime`,
  `print_items.rs:479`). Composition is by pointer (`RcPath`) and `set_next` splicing
  (`print_items.rs:395`), not by constructing nested `Vec<Doc>`.

### 1.3 How the printer resolves conditions and widths

Single-pass, backtracking printer in `crates/core/src/formatting/printer.rs`. Main loop
`inner_print` (`printer.rs:148`) walks `current_node`, dispatching in `handle_print_node`
(`printer.rs:289`).

**Width tracking.** The `Writer` tracks the current column; `is_above_max_width(offset)`
(`printer.rs:347`) = `writer.column_number() + offset > max_width`. `max_width` comes from
`PrinterOptions` (`printer.rs:39`).

**Break points via save points (the backtracking core).**
- On `SpaceOrNewLine`/`PossibleNewLine`, when there is room, the printer records a
  `possible_new_line_save_point` (`mark_possible_new_line_if_able`, `printer.rs:335`) capturing
  the entire writer + node state.
- When a subsequent `String` would exceed `max_width`
  (`handle_string`, `printer.rs:595`) it **rewinds** to that save point and forces a newline
  (`update_state_to_save_point(save_point, true)`, `printer.rs:351`). `SpaceOrNewLine` does the
  same eagerly (`printer.rs:404`). This is dprint's equivalent of Prettier's "group doesn't fit →
  break", but done by *restore-and-retry* rather than by measuring ahead.
- `new_line_group_depth` gives lower-precedence break points: a save point is only replaced by a
  shallower/equal group depth (`printer.rs:336`, `printer.rs:411`), so outer groups break before
  inner ones.

**Condition resolution** — `handle_condition` (`printer.rs:548`):
1. Optionally store a save point for reevaluation (`store_save_point`).
2. Call the resolver with a `ConditionResolverContext` wrapping current `WriterInfo`
   (`print_items.rs:556`).
3. If stored, cache the value in `resolved_conditions`.
4. If a **look-ahead save point** was registered for this condition id (because something earlier
   asked for its value while it was still `None`), rewind to it now that it's known
   (`printer.rs:561`).
5. Otherwise follow `true_path` or `false_path` by redirecting `current_node`
   (`printer.rs:568`).

**Info resolution + look-ahead** — `handle_targeted_info` (`printer.rs:461`): when the printer
reaches an `Info`, it records the actual line/column/etc. into fast maps
(`resolved_line_numbers`, `resolved_is_start_of_lines`, … `printer.rs:61`). If any earlier
condition asked for that info before it was known, a look-ahead save point was stored
(`resolved_line_number`, `printer.rs:191`); reaching the info **rewinds** the printer back to the
asking site, now with the value available. This is what lets a condition legitimately depend on a
position that occurs later in the output.

**Reevaluation** — `handle_condition_reevaluation` (`printer.rs:519`): re-runs a stored condition
at a later point; if its value changed, rewind to its save point. Guarded by
`InfiniteReevaluationProtector` so ping-ponging conditions terminate (with a logged error at the
cap). See the comment referencing dprint-plugin-typescript issue #372 in
`gen_separated_values.rs:544`.

Net effect: **one forward pass with targeted rewinds**, rather than Prettier's
"measure-then-commit". Complexity is managed by only creating save points where a condition/info
actually needs look-ahead.

---

## 2. Conditions & Infos — how a plugin drives layout from input properties

Pattern: **emit an `Info` (probe) → later read it in a `Condition` resolver → branch layout.**
Because resolvers are arbitrary closures over `ConditionResolverContext`
(`print_items.rs:1000`), a plugin can base layout on *any* measurable property.

Constructors/helpers (`crates/core/src/formatting/conditions.rs`):
- `if_true` / `if_true_or` / `if_false` (`conditions.rs:114`) — build a `Condition` from a
  resolver + true/false paths.
- `if_above_width_or(width, a, b)` (`conditions.rs:99`) — pick based on current column vs. width.
- `new_line_if_multiple_lines_space_or_new_line_otherwise(start_ln, end_ln)`
  (`conditions.rs:63`) — newline iff the region actually spans multiple lines; otherwise a
  soft space/newline.
- `new_line_if_hanging` (`conditions.rs:55`) — newline iff the construct is "hanging"
  (indent level increased).

Resolver context API (`print_items.rs:1007`): `resolved_condition(&ref)`,
`resolved_line_number(ln)`, `resolved_is_start_of_line(...)`, `resolved_line_start_indent_level`,
`is_forcing_no_newlines`, plus `clear_info` (invalidate a probe so it re-resolves).

**References and reuse.** `Condition::create_reference()` (`print_items.rs:944`) returns a
`ConditionReference` whose `create_resolver()` (`print_items.rs:981`) yields a resolver that reads
that condition's resolved value — so *one* "is this multi-line?" decision can be shared by dozens
of downstream conditions (separators, indents, trailing commas). This is the key composition
primitive.

**Worked example — `gen_separated_values`** (`crates/core/src/formatting/ir_helpers/gen_separated_values.rs`),
used for arrays, object literals, params, args, imports, tuples, unions, etc. It builds ONE
`is_multi_line` condition and threads its reference through every element:
- Choice of the multi-line predicate (`gen_separated_values.rs:184`):
  - `force_use_new_lines` → `Condition::new_true()` (always expand),
  - else `prefer_hanging` → `get_is_multi_line_for_hanging` (`:503`),
  - else → `get_is_multi_line_for_multi_line` (`:529`), which looks at the resolved line numbers
    of each value (`value_data.line_number`, `value_data.is_start_of_line`) and returns true if
    any value starts on its own line or a value spans multiple lines
    (`check_value_should_make_multi_line`, `:621`).
- `is_multi_line_condition_ref` (`:198`) is then reused by every separator
  (`multiLineOrHangingCondition`, `:348`) so all items break together — the emergent equivalent of
  a Prettier "group".
- Positions are re-probed when they move: `clear_resolutions_on_position_change` (`:455`) clears
  all the per-value infos when the start column/line changes, forcing re-resolution — avoiding
  stale decisions during backtracking.

Takeaway for us: this is a clean model for "the layout of a collection is a **function of the
resolved geometry of its parts**", and the parts can vote (e.g. `allow_inline_multi_line` /
`allow_inline_single_line` per value, `GeneratedValue`, `:132`) — directly relevant to allowing
several valid forms and choosing among them by input properties.

---

## 3. Data model in the TS plugin — AST via swc (deno_ast), print-item generation, comments

- **Parser = swc, wrapped by `deno_ast`.** `src/swc.rs:47` calls `deno_ast::parse_program(...)`
  with `capture_tokens: true`, `scope_analysis: false`. Media type / syntax derived from file
  extension (`swc.rs:36`); on failure it retries as `.tsx`/`.jsx` (`swc.rs:15`) so JSX is
  auto-detected. Decorators enabled even for JS (`swc.rs:44`). Selected syntax errors are treated
  as fatal (`ensure_no_specific_syntax_errors`, `swc.rs:107`).
- **AST → print items.** `src/generation/generate.rs` (~10k lines) is a big
  `gen_node`/`gen_*` dispatch that walks the `deno_ast::view` typed AST and returns `PrintItems`.
  It uses the core `ir_helpers` (`gen_separated_values`, `gen_surrounded_by_tokens` at
  `generate.rs:9564`, `gen_membered_body`, …). Traversal uses **original source tokens** heavily
  via `context.token_finder` and `node.tokens_fast(...)` to find braces/commas
  (e.g. `generate.rs:7192`).
- **Comments are not in the AST**, so they are attached from swc's comment store and tracked to
  avoid double-emission:
  - `CommentTracker` (`src/generation/comments.rs:10`) hands out "leading comments + all
    previously-unhandled comments" (`leading_comments_with_previous`, `:26`) and trailing
    comments (`:55`), walking captured tokens so every comment is emitted exactly once in source
    order.
  - `Context` records handled comments (`context.has_handled_comment(...)`, used at
    `generate.rs:7289`) to dedupe.
  - Comment *positioning* helpers in `src/generation/node_helpers.rs`:
    `has_leading_comment_on_different_line` (`:46`), `has_surrounding_different_line_comments`
    (`:76`). These feed the multi-line decision (a comment on its own line forces expansion —
    see §4).
  - Line comments emit `Signal::ExpectNewLine` semantics so the following token lands on a new
    line (printer treats `ExpectNewLine` as always-allowed, `printer.rs:394`).

---

## 4. Preservation of the user's original formatting — THE key behavior for us

dprint deliberately preserves **one specific input property: whether the construct was written
expanded (a newline right after the open token) or collapsed.** It does NOT preserve arbitrary
whitespace; it re-derives everything else. This is the "keep multiline if the user wrote it
multiline" rule.

### 4.1 The detection: `force_use_new_lines`

For collections/arg-lists/etc., the plugin computes a boolean `force_use_new_lines` and passes it
into `gen_separated_values` (where `true` ⇒ `Condition::new_true()`, i.e. always expand,
`gen_separated_values.rs:185`).

Array example — `get_force_use_new_lines` (`generate.rs:7185`):
```rust
fn get_force_use_new_lines(node, nodes, prefer_single_line, context) -> bool {
  if nodes.is_empty() { false }
  else if prefer_single_line {
    // only comments on their own line force multi-line
    has_any_node_comment_on_different_line(nodes, context)
  } else {
    // find the "[" and check: is the first element on a different line than "["?
    let open_bracket_token = ...Token::LBracket...;
    node_helpers::get_use_new_lines_for_nodes(&open_bracket_token.range(), &nodes[0], context.program)
  }
}
```

The primitive test — `node_helpers.rs:42`:
```rust
pub fn get_use_new_lines_for_nodes(first, second, program) -> bool {
  first.end_line_fast(program) != second.start_line_fast(program)
}
```

So: **if the user put a newline between `[` (or `{` / `(`) and the first element, the collection
is forced expanded**; otherwise it is laid out normally (collapse if it fits, break only on
overflow). The same helper `get_use_new_lines_for_nodes_with_preceeding_token("{"/"(", …)`
(`generate.rs:9889`) is reused for objects, params, args, etc. Binary expressions use the same
idea between the first and last operand (`generate.rs:2010`).

`has_any_node_comment_on_different_line` (`generate.rs:9931`) is the escape hatch that still forces
expansion (even in `preferSingleLine` mode) when a comment sits on its own line.

### 4.2 The mechanism once forced

`force_use_new_lines == true` sets the shared `is_multi_line` condition to always-true, so every
separator in `gen_separated_values` takes its `NewLine` `true_path` and the whole collection
expands consistently (§2). When not forced, `is_multi_line` is the *geometry-based* predicate
(`get_is_multi_line_for_multi_line`) and the collection collapses when it fits.

### 4.3 `preferSingleLine` config toggles the rule off

When `preferSingleLine == true` for a node kind, the "newline after open token" signal is
**ignored** — only own-line comments force expansion (`generate.rs:7188`, `:9899`, `:9922`). i.e.
`preferSingleLine=true` = "always try to collapse; don't respect the user's expansion".
Default is `false` (`resolve_config.rs:59`), so **by default dprint respects the user's
expansion choice.** (One built-in exception: `conditionalExpression.preferSingleLine` defaults to
`true`, `builder.rs:65`.)

### 4.4 Other "maintain original line breaks" mechanisms

- `MultiLineOptions::maintain_line_breaks()` (`gen_separated_values.rs:115`) — in multi-line mode,
  insert a hard newline between two values **only if they were on different lines in the source**
  (`has_new_line` computed from `lines_span`, `gen_separated_values.rs:334`, applied at `:357`);
  otherwise use a soft space-or-newline. Used e.g. by binary expressions (`generate.rs:2030`).
- `allow_blank_lines` + `LinesSpan` (`gen_separated_values.rs:126`, `:334`) — a single blank line
  between elements is preserved (collapsing 2+ blank lines to 1), by comparing source line spans.
- Statement level: `has_separating_blank_line` (`node_helpers.rs:25`) preserves one blank line
  between statements (`generate.rs:7312`).

### 4.5 Relevance to a "multiple valid forms" formatter

This is essentially the model we want: the formatter treats "expanded" and "collapsed" as **two
valid forms**, and selects between them using a **cheap, local property of the input** (newline
after the open delimiter) rather than reformatting to a single canonical form. The property is:
- **stable/idempotent** (re-formatting expanded output keeps it expanded, because the newline is
  still there), and
- **overridable per-construct via config** (`preferSingleLine`).
For us, the generalization is: define the discriminating input property per construct, force the
corresponding valid form, and otherwise let width drive the choice.

---

## 5. Configurability — layout choices exposed as config

- Config is resolved into a flat `Configuration` struct (`src/configuration/types.rs`, ~667 lines)
  via `resolve_config.rs`; a fluent `ConfigurationBuilder` (`builder.rs`) mirrors every field.
- **Global defaults cascade into per-node overrides.** e.g. a top-level `preferSingleLine`
  (`resolve_config.rs:58`, default `false`) is the fallback for
  `arrayExpression.preferSingleLine`, `object...`, `arguments...`, `parameters...`,
  `binaryExpression...`, `typeLiteral...`, `tupleType...`, `unionAndIntersectionType...`, etc.
  (`resolve_config.rs:232`+). Same cascade pattern for other families (quote style, semicolons,
  trailing commas, brace/operator position, `useBraces`, `...prefer_hanging`, `...spaceAround`,
  sort orders, `line_per_expression`, etc.).
- So essentially **every place where dprint makes a break/no-break or hang/expand decision is a
  named config knob**, defaulting from a broader knob. The generation code reads
  `context.config.<node>_<option>` inline at each `gen_*` site (see the grep of
  `*_prefer_single_line` in `generate.rs`: array, object, arguments, parameters, jsxAttributes,
  forStatement, variableStatement, memberExpression, computed, union/intersection, mappedType,
  conditionalType, tupleType, typeParameters, …).

---

## Key file:line index
- IR types: `dprint/crates/core/src/formatting/print_items.rs:485` (PrintItem), `:496` (Signal),
  `:578` (Info), `:857` (Condition), `:1000` (ConditionResolverContext).
- Printer/backtracking: `.../printer.rs:148` (loop), `:335` (save points), `:461`
  (info+look-ahead), `:548` (conditions), `:595` (width-driven rewind).
- Condition builders/predicates: `.../conditions.rs`, `.../condition_helpers.rs`,
  `.../condition_resolvers.rs`.
- Separated values (multi-line engine): `.../ir_helpers/gen_separated_values.rs`.
- TS parse: `dprint-plugin-typescript/src/swc.rs:47`.
- TS generation: `.../src/generation/generate.rs` (`get_force_use_new_lines` `:7185`,
  `get_use_new_lines_for_nodes_with_preceeding_token` `:9889`,
  `has_any_node_comment_on_different_line` `:9931`).
- Comments: `.../src/generation/comments.rs`, `.../src/generation/node_helpers.rs:42`.
- Config: `.../src/configuration/{types.rs,resolve_config.rs,builder.rs}`.
