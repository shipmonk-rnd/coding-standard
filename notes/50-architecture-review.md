# Architecture review — PoC of the permissive whitelist formatter

Reviewed: all of `poc/src/` (35 classes, ~2k lines), `tests/run.php`, fixtures, notes
00/02/03/04/40. Design pillars (whitelist, no width, vertical-free/horizontal-strict,
verify gate) taken as given — not relitigated.

## Executive verdict

The architecture is **sound and empirically validated** — the project-then-render trick
(read choice points off the source, render, compare) genuinely delivers
MATCH/REPAIR/FATAL without search, and the corpus numbers prove the model scales to real
code. Graduate it.

But there is one **root-cause defect** that manufactures most of the listed friction:
**`render(): string` — each node returns an opaque string.** Because parents receive
strings, (a) indentation must be threaded as ambiguous ints (friction 1), (b) comments
must be smuggled through as wrapper nodes and bespoke fields (friction 2), (c) blank-line
policy is re-decided in every parent (friction 6), (d) there is no per-node source↔output
correspondence, so MATCH/violations are whole-file only (friction 4), and (e) every
wrapper must re-delegate `firstToken()` (friction 9). Replace string concatenation with a
small **Emitter (token writer)** before scaling the grammar; keep the imperative
templates. That single change collapses five of the nine frictions into one mechanism.

Secondary root cause: **the parser both recognizes grammar and enforces the standard**
(comment-placement guards, `else if` fatal, trailing-comment attachment heuristics live
in `Parser`). The "allowed set" is smeared across parser guards + render methods, which
is why every new comment spot costs a guard *and* render logic. The emitter model moves
enforcement out of the parser: parser recognizes, emitter enforces.

---

## Friction points

### 1. `render(int $depth)` ambiguity — VALIDATED, and precisely diagnosable

The contract conflates two coordinates that are genuinely different numbers:

- **lineIndent** — indent of the line the node's first token sits on;
- **contIndent** — indent for continuation lines the node itself opens.

They differ depending on *where on its line the node starts*: a `BinChain` after
`$x = ` continues at `stmtDepth + 1`, but the same `BinChain` as the sole content of a
broken `Cond` starts at `depth+1` and continues at `depth+1` (leading operators aligned
with the first operand). No single int can encode that; hence `renderAt($lineIndent,
$firstDepth)` and Cond's special cases. This will get worse: `=> ` bodies of arrow fns,
broken assignment RHS, `return <chain>` all have the same dual nature.

**Recommendation:** make the two-coordinate contract THE contract, as a value object:

```php
final class RenderCtx {
    public function __construct(
        public readonly int $line,  // indent of the node's anchor line
        public readonly int $cont,  // indent for continuation lines this node opens
    ) {}
    public function nested(): self   { return new self($this->line, $this->line + 1); } // mid-line child
    public function aligned(): self  { return new self($this->line, $this->line); }     // cond-style
    public function atLine(int $d): self { return new self($d, $d + 1); }
}
interface Node { public function render(RenderCtx $ctx): string; }
```

The parent — the only party that knows whether the child starts a fresh line — computes
the child ctx. `renderAt`, `hasJointBreaks`-driven special cases in `Cond`, and the
`instanceof BinChain|Ternary` checks all disappear (`Cond` just passes
`$ctx->aligned()`-at-depth+1 to *any* expression, and expressions that never break
ignore `cont`). Do this even if you do nothing else; it is cheap now and each new
expression template compounds the bug surface.

Note the `instanceof BinChain || instanceof Ternary` dispatch in `Cond::render()` is a
second smell of the same disease: layout knowledge about children leaking into the
parent. With `RenderCtx` it becomes uniform.

### 2. Trailing/attached comments — VALIDATED; there IS a general model

The eight bespoke placements share one shape: *a same-line `//` comment must end its
line, anchored to the token it follows*. Every parse guard re-proves "the next token
will start a new line" and every render site re-emits `' ' . $comment->text`. The
generalization is the classic **trailing-trivia** model (Roslyn/Biome), adapted:

- **Attachment (one place, in the parser's `next()`/a helper):** after consuming any
  significant token, if the next token is a same-line comment, attach it as
  `trailing` trivia to the just-consumed token. No per-construct guards.
- **Emission (one place, in the Emitter):** when a token carrying trailing trivia is
  emitted, the trivia goes into a pending slot. The next emitter operation must be a
  newline (of any kind); the emitter then flushes `' ' . comment` before the `\n`.
  If the template instead emits a token/space next → the comment has no legal home →
  FATAL (or verbatim-fallback, see #4) — *one* runtime invariant replacing ~8 guards.
- Own-line comments stay what they are: `CommentRow`/`CommentStmt` pseudo-items at the
  places templates explicitly allow them.

This is strictly more permissive than today only where a newline already follows —
i.e. exactly the "line-targeted directive" cases — and it automatically covers every
spot corpus work keeps discovering (trailing comment after `=>`, after a closing paren
of a broken call, etc.). It also deletes `TrailingComment`, the mutable
`Segment::$trailingComment` hack, `Cond`'s comment field, `Block::$headerComment`,
`FunctionDecl::$headerComment`, and the opener-comment slice in `CollectionLayout`.

The one policy decision to make deliberately: a trailing comment *forces* the following
joint broken (it already does today via the parser guards — keep that, but now the
emitter enforces it uniformly).

Note this requires the Emitter (#0 above): with `render(): string` there is no "next
operation" to observe, which is exactly why you ended up with wrapper nodes.

### 3. Imperative templates vs declarative IR — REFUTE the IR (for now), but reify two things

A full declarative Template IR is **premature**. Evidence: 35 imperative templates
already pass 4 corpora; an IR would be a second language with its own interpreter,
debugging story, and escape hatches (PHP grammar has enough irregularity — promoted
params, match's mandatory verticality, Allman-vs-same-line braces — that the IR would
sprout `custom()` nodes fast, which is the worst of both worlds). Prettier/Biome need a
Doc IR because a *width printer* must interpret it; you have no printer.

What you actually need from "templates as data" is: (a) enumerable choice points for
config/docs, (b) checkable allowed-set, (c) per-node MATCH. Get those cheaper:

- **Reify the joint vocabulary**, not the templates. The Emitter's API *is* the
  vocabulary from notes/40 §1: `none() / space() / break() / blankableBreak() /
  spaceOrBreak(observed) / …`. Config toggles then attach to named joints
  (`'call.args' => spaceOrBreak`), and the standard's documentation is the emitter call
  sites — greppable, testable, and impossible to bypass (templates physically cannot
  emit whitespace except through joints).
- **Per-node MATCH** comes from emitter span tracking (#4), not from an IR.
- Revisit the IR only if/when config demands "user-defined layouts", which the design
  explicitly rules out ("options never invent layouts", notes/40 §6). Given that, the
  IR may *never* pay for itself.

### 4. Whole-file granularity — VALIDATED; fix before scaling, in three layers

This is the most important *product* gap: at 10x grammar, fatals become rarer but files
become bigger, and "one exotic construct = whole file frozen" plus "`--check` says
changed, guess where" is not shippable.

- **Layer 1 — statement-level error recovery.** On `FatalError` inside a statement,
  resync: rewind to the statement's first token, scan forward with depth-aware bracket
  counting to the statement terminator (`;` or balanced `}`), wrap the span as
  `VerbatimSpan`, record a `Violation(kind: unsupported, line, message)`. The machinery
  (VerbatimSpan, token indices) exists; the parser needs to track token *positions* so
  it can rewind (`$startPos = $this->pos` at each `parseStmt`). Ruff/biome-style, and it
  turns the fatal-rate metric into a per-construct histogram for free.
  Caveat: a verbatim statement's *interior* is frozen but its own-line indentation
  should still be left untouched (emit gap verbatim including leading whitespace).
- **Layer 2 — per-node outcomes.** Give `SigToken` a byte `offset`; the Emitter records
  `(node, sourceSpan, outputSpan)` on enter/exit. `outputSlice !== sourceSlice` ⇒ that
  node was repaired. Whole-file MATCH = root unchanged (identical to today's check).
- **Layer 3 — violation identity.** Don't invent a rule-ID taxonomy per template.
  Violations fall out of the joint layer: when a joint's emitted gap differs from the
  observed source gap, record `Violation(joint: 'space-after-comma' | 'indent' |
  'trailing-comma' | …, line, expected, actual)`. Joint kinds + a handful of structural
  triggers (≥2 params, comma-iff-broken) ARE the rule names — a small, stable, documented
  set, which is exactly what a `--check` CI UX and a future baseline file need.

### 5. Hand-rolled parser — SOUND; keep it, but harden differently

Keep hand-rolling. The reasons are structural, not sentimental:

- The layout tree is deliberately *not* an AST: flat `BinChain` (no precedence),
  `AccessChain` segments, `Cond` as a construct — nikic's AST would have to be
  re-lowered into these shapes anyway, and its trivia loss remains disqualifying
  (notes/17 stands).
- `TOKEN_PARSE` guarantees the input is **valid PHP**, so the parser only has to
  *recognize*, never validate. Exploit this harder: prefer permissive token-shape
  recognition (the `expectMemberName()` regex is the right spirit) over enumerating
  token IDs. Precedence-free `parseExpr` is correct for a formatter precisely because
  of this guarantee — document that as intentional.
- The remaining gaps (anonymous classes, `list()`/destructuring, `->{$expr}`,
  `new ($expr)`, `goto`, alternative syntax) are a bounded, known list, and statement
  recovery (#4) converts each from "file frozen" to "statement frozen".

Two hardening measures instead of switching parsers:

- **Differential oracle in CI only**: run nikic/PHP-Parser over the corpus and assert
  the layout parser consumed files it parses (and bucket fatal messages). Dev-dependency
  only; zero runtime coupling. This addresses "duplicates knowledge PHP has" at the
  testing layer, where duplication is a feature.
- **Whitespace-perturbation fuzz**: for any corpus file `x` that formats cleanly,
  assert `format(perturbHorizontalWs(x)) === format(x)`. Cheap, targets exactly the
  repair paths, and would have caught the depth bugs from friction 1.

One real weakness to fix: `Parser` fragility around lookahead heuristics
(`looksLikeClassDecl`, named-arg `T_STRING ':'`, `isAmp` by text). Fine today; add a
regression fixture per heuristic because each is a latent misparse at 10x grammar.

### 6. Blank-line policy duplication — VALIDATED; solved by the Emitter

Seven re-implementations of "own line, 0–1 blank preserved", already with drift
(ClassDecl's empty-body special case vs Block's; MatchExpr inlines what StmtSeries
does). With the Emitter it is one method:
`$e->lineBreak(observed: $tok->newlinesBefore(), allowBlank: true, indent: $d)` —
clamping (≥2 → 1) lives in exactly one place, and "forced vs preserved blank" becomes
an explicit parameter instead of a copy-paste variation. Do not fix this by extracting
a static helper into the current string world — you'd keep the drift risk at every call
site that forgets the helper; the emitter makes raw `"\n"` emission impossible.

### 7. CollectionLayout flag creep — PARTIALLY validated; restructure, don't panic

Two booleans is not yet a crisis; the real problem is the **closure-state row machine**
(`$rowOpen`/`$pendingComment`/`$closeRow`) — imperative state encoding what is actually
a projection. Split it:

```php
// projection (pure, testable, per-collection):
/** @return list<Row|CommentRow>  Row = { items: non-empty-list<ListItem>, blankBefore: bool, trailing: ?SigToken } */
function projectRows(array $items, bool $onePerRow): array;
// render: trivial fold over rows via the emitter
```

`forceBroken` stays a parameter (it is a genuine structural trigger). The
opener-comment slice and pending-comment state disappear via #2. Bonus: `Row` objects
are the per-collection "choice assignment" as data — the useful 10% of the Template IR,
obtained locally. Watch the flag count after this refactor; if a third boolean appears
(e.g. "no trailing comma in this bracket kind"), switch to a small
`CollectionStyle` value object rather than more parameters.

### 8. Verifier allowances — VALIDATED, and there are two REAL BUGS here today

**Bug 1:** match arms. `MatchArm::render()` always emits `,`, but the added-comma
allowance only accepts `]`/`)` as the following token — never `}`. Repairing
`default => 0` (no trailing comma) before `}` renders correctly, then the **verifier
rejects its own correct output** as an internal error and the file is reported fatal.

**Bug 2:** trailing comma before `=>` in a match condition list (`1, 2, => x` — valid
PHP). `parseMatch` silently drops it; the verifier sees input `,` vs output `=>`,
neither allowance applies → same false internal fatal.

Both are the predictable cost of hardcoded pairwise skip rules. **Model:** replace
positional skipping with **normalization**: `normalize(list<SigToken>): list<string>`
that (a) deletes any `,` whose next significant token is a closer (`]`, `)`, `}`) or
`=>`-in-match, (b) strips per-line leading whitespace inside comments; then require the
two normalized streams to be *exactly* equal. Allowances become named, unit-testable
functions applied to both sides symmetrically.

For future *token-adding* moves (parens on `new`): do NOT widen global allowances — that
weakens the gate for every file. Instead have templates **declare edits**
(`DeclaredEdit(kind: insert, token: '(', at: offset)`) collected during rendering; the
verifier consumes declared edits and rejects any undeclared difference. The gate stays
maximally strict per file.

Also: `Formatter::format()` catches only `FatalError`. Any plain PHP `Error` (e.g. a
null-property access in a template — `Segment::render()` dereferences nullable fields
by kind convention) escapes and kills a batch run. Catch `Throwable` at the file
boundary, report as internal fatal, return input unchanged — the "never corrupt, never
crash the run" story should not depend on templates being exception-free.

### 9. firstToken()/gapBefore delegation — VALIDATED; mostly dissolved by #2, finish with types

Two-thirds of the wrapper problem disappears when `TrailingComment`/`AttributedNode`
stop being layout wrappers (trailing trivia; attributes could stay a node but with the
emitter its `firstToken` is only needed for the blank-line observable). For what
remains, make the invariant structural:

- `abstract class LayoutNode` with `final public function newlinesBefore(): int
  { return $this->firstToken()->newlinesBefore(); }` — parents stop reaching through
  `firstToken()` for the observable, so a wrong delegation has one consumer, not many.
- Verifier/emitter cross-check (debug mode): the first token actually emitted for a
  node must be `firstToken()` — turns silent misbehavior into a loud assert.

---

## Issues NOT on your list

1. **Verifier false-fatal bugs** (see #8) — worth fixing immediately; they contradict
   the "0 verifier failures" claim for any corpus file with those shapes.
2. **Line endings are silently rewritten.** `newlinesBefore()` counts `\n`; rendering
   emits only `\n`. A CRLF file is wholesale rewritten to LF and *every* line reported
   changed — probably desirable (enforce LF) but currently an accident. Decide, document
   as a rule, add a fixture; also consider a fast-path fatal ("CRLF input") vs repair.
3. **Kind-tagged `Segment` is a type hole.** Seven kinds multiplexed over nullable
   fields with `match` dispatch; `AttrGroup`'s `array{...}` tuples likewise. At 10x
   grammar these become the main NPE source (cf. the `Throwable` gap above). Sealed
   per-kind classes (or at minimum constructor asserts per kind) before adding more
   segment kinds (`->{$expr}`, `?->` invoke, etc.).
4. **`ParenExpr`/`ForStmt`/`INDEX` flatten author breaks.** Documented as PoC limits,
   but note they quietly violate the core invariant ("vertical is the author's choice")
   rather than fataling. That's a policy inconsistency: everywhere else, "no broken form
   in the template" is a repair-to-flat *by design decision*; make the list of
   deliberately-flat constructs explicit in the docs/notes, or these will ossify by
   accident. (The verifier saves you from the worst case — a `//` comment swallowed into
   a joined line changes the token stream and is rejected — but only as a crash-late
   backstop.)
5. **No ignore/pragma mechanism.** Adoption on real repos needs `fmt:off`-style regions
   or at least per-file opt-out, plus a baseline story for `--check`. VerbatimSpan +
   statement recovery (#4) gives you the mechanism nearly free; plan the syntax now so
   corpus users aren't blocked.
6. **Shebang/`#!` files and `<?php` variants** fatal at `parseFile` (`expect(T_OPEN_TAG)`).
   Fine for libraries; document, and make the error message say so.
7. **Test harness gap:** `tests/run.php` is golden-files only — no unit tests for
   Verifier normalization, projection helpers, or parser heuristics. The two verifier
   bugs above are exactly the kind of thing a 20-line unit test file catches. Also the
   corpus runs appear to be manual; wire the corpus + fatal-histogram + perturbation
   fuzz (#5) into CI before grammar work resumes, so every new construct lands with a
   measurable coverage delta.

---

## Priorities

**Do BEFORE scaling the grammar further** (each is cheap now, expensive at 100 node classes):

1. Fix Verifier bugs (match `}` comma, comma-before-`=>`); switch to the normalization
   model; catch `Throwable` at the file boundary. *(hours)*
2. `RenderCtx { line, cont }` — kill `render(int)`/`renderAt` duality and the
   `instanceof` dispatch in `Cond`. *(a day)*
3. **Emitter/token-writer + trailing-trivia comments.** The big one: replaces the 8
   comment placements, centralizes blank-line/indent policy, enables per-node spans.
   Do it at 35 classes, not 100 — every new template written against `render(): string`
   is migration debt. *(days)*
4. Statement-level recovery + `Violation` reporting (needs #3's spans); `--check` output
   becomes real. *(days)*
5. `projectRows()` refactor of CollectionLayout. *(a day, after #3)*
6. CI: corpus + fatal histogram + whitespace-perturbation fuzz + PHP-Parser differential
   oracle. *(a day)*

**Can wait:**

- Declarative Template IR (possibly forever — see #3 verdict), config schema/docs
  generation (blocked on joint reification, which #3 above delivers anyway).
- Sealed Segment classes (do opportunistically when touching chains).
- Pragma/ignore + baseline (before first external adoption, not before grammar work).
- CRLF policy implementation (decide now, implement trivially later), shebang handling,
  performance/parallelism (4ms/file needs nothing).

## Graduation verdict

**Yes — graduate, no rethink needed.** The core bet (whitelist via project-then-render,
observable choice points, no width printer, verify gate) is novel, coherent, and now
corpus-proven; none of the frictions indicts it. What the PoC got wrong is uniformly
*mechanical substrate* (strings instead of an emitter, ints instead of a context,
wrappers instead of trivia, skip-rules instead of normalization) — all local,
well-understood refactors with clear shapes, and all dramatically cheaper before the
grammar grows. The only thing I'd call a genuine design debt rather than a refactor is
per-node outcomes/recovery (#4): a whitelist formatter that freezes whole files on one
exotic statement will not survive contact with a large legacy monorepo, so treat it as
part of the architecture, not a UX feature.
