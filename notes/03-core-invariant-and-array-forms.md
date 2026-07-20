# Core invariant + allowed-form examples (user-provided)

## THE core invariant (derived from user's array examples, 2026-07-17)

> **Vertical layout is the author's choice and is PRESERVED. Horizontal whitespace is
> NORMALIZED by rules.**

- **Vertical** = where line breaks go and where blank lines go (whether a construct is on
  one line or many, how elements are grouped across lines, blank lines between them).
  The formatter does NOT impose this. It preserves what the author wrote.
- **Horizontal** = spaces/tabs *within* a line (space after comma, spacing around
  operators, no space just inside `[`/`(`, indentation of a line). The formatter DOES
  normalize this to the single allowed spacing.

**Permissiveness is NARROW and surgical — the tool still enforces a LOT.** The only thing
relaxed is the *vertical arrangement*: where line breaks and blank lines fall, how many
elements sit on a line, row grouping. Everything else is strictly enforced and
deterministic — comma placement (one space after, none before; trailing comma iff
multiline), indentation (each line snapped to its nesting depth, spaces not tabs), bracket
spacing, spacing around operators/keywords/`=>`, etc. So the rule *count* is large; the
permissiveness is a single, well-defined carve-out, not a general "anything goes".

NOTE — indentation enforcement specifically requires the **structural/depth layer**: to
force "one level deeper than the statement" we must know each token's nesting depth, which
needs bracket-matching / parent links (the CST-lite in `30`), not a flat token list. This
is the rule class a naive token-only tool gets wrong.

This single principle implies almost everything else:
- It implies **no width-based rules** (a max-line-length would override the author's
  vertical choice → contradiction). Width-free is a *consequence* of this invariant.
- It implies **multiple allowed forms** (the author's vertical layout is accepted as-is).
- It implies a **localized-edit / trivia-aware engine**: we edit horizontal whitespace
  trivia in place and leave line-structure trivia alone — we do NOT re-render constructs
  through a layout engine (that would destroy the preserved vertical layout).
- Exceptions are **structural triggers**, not layout preferences: e.g. "multiline ⇒
  require trailing comma", "single-line ⇒ forbid trailing comma", count-based "≥N params ⇒
  multiline". These constrain vertical layout only at the edges; they don't reflow.

This is MORE permissive than every formatter researched (dprint/mago/Prettier expand to
one-element-per-line; we don't). Confirms none of their layout engines apply — only their
trivia/CST data model + comment handling + verification ideas do (see `02`).

## Array — allowed forms (all VALID, none rewritten into another)

```php
// 1. single line, no trailing comma, one space after comma, no space inside brackets
$a = [1, 2];

// 2. fully expanded, one element per line, trailing comma
$a = [
    1,
    2,
];

// 3. expanded, MULTIPLE elements per line (author's row grouping preserved), trailing comma
$a = [
    1, 2,
    3, 4,
];

// 4. expanded, rows grouped with BLANK LINES between groups (preserved), trailing comma
$a = [
    1, 2,

    3, 4,
    5, 6,
];
```

### What the formatter WOULD still normalize inside these (horizontal only)
- exactly one space after each `,` (so `1,2` → `1, 2`); no space before `,`
- no space just inside `[` / `]` on single-line (`[ 1, 2 ]` → `[1, 2]`)
- indentation of each continuation line = one level deeper than the line with `$a`
  (spaces, per existing tab-width=4 / DisallowTabIndent)
- closing `]` on its own line aligned with the statement (for multiline forms)
- trailing comma: present iff multiline (structural trigger, not layout)
- collapse runs of >1 blank line? OPEN QUESTION — example 4 has a single blank line;
  need to decide if 2+ blank lines inside a collection are clamped to 1 (most tools do)
  or preserved. Likely clamp to 1 for consistency with blank-line rules elsewhere.

### What it must NOT do
- must NOT move elements to one-per-line, or join rows, or change how many elements are
  on a line, or remove the author's blank-line grouping. That vertical structure is the
  author's and is preserved.

## To generalize (D7 content, later)
Enumerate the same "allowed vertical forms + normalized horizontal whitespace + structural
triggers" for: call arguments, parameter lists, match arms, array destructuring, chained
method calls, binary/logical operator chains, ternaries, `use` import lists, attributes,
class member layout. Each follows the SAME invariant; the only per-construct specifics are
the structural triggers (e.g. trailing comma applicability, count-based multiline).
