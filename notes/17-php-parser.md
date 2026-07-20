# nikic/PHP-Parser — format-preserving pretty printer (research)

Repo: `/p/shipmonk/+tasks/oss-coding-standard-from-scratch/repos/PHP-Parser`
Version: 5.x (`composer.json` branch-alias `5.x-dev`), runs on PHP >= 7.4, parses PHP 7.0–8.4.
License: **BSD-3-Clause** (`LICENSE`, `composer.json:14`). Last commit 2026-07-11 — actively maintained.

Evaluated as the AST-based engine for a pure-PHP, permissive, no-width, localized-edit formatter.

---

## 1. AST + token model

- **AST**: every node extends `NodeAbstract` (`lib/PhpParser/NodeAbstract.php:5`). Attributes live in a
  free-form `array<string,mixed> $attributes` (`:6`). Structural children are typed public properties;
  `getSubNodeNames()` enumerates them (used everywhere in the printer, e.g. `PrettyPrinterAbstract.php:649`).
- **Token positions are always recorded** (this changed vs v4 where they were opt-in). The parser's
  `getAttributes(startTokenPos, endTokenPos)` unconditionally writes `startLine/startTokenPos/startFilePos`
  and `endLine/endTokenPos/endFilePos` on every node (`lib/PhpParser/ParserAbstract.php:488-499`). Accessors:
  `getStartTokenPos()/getEndTokenPos()/getStartFilePos()/getEndFilePos()` (`NodeAbstract.php:61-98`).
  These token offsets are the index into the token array and are the backbone of format preservation.
- **Lexer/tokenizer**: `lib/PhpParser/Lexer.php`. `tokenize()` delegates to PHP's native
  `PhpToken::tokenize()` (via `Token::tokenize`, `Lexer.php:31`) and **keeps whitespace and comments**
  (`Lexer.php:16-17`), appending a sentinel token id `0` (`:114`). Tokens are `PhpParser\Token`
  (`lib/PhpParser/Token.php`, extends the native `PhpToken` polyfill) exposing `id`, `text`, `pos`
  (byte offset), `line`, plus `getEndPos()`. Obtain the token array with `$parser->getTokens()`
  (`ParserAbstract.php:224`) after parsing — it is what you pass to the format-preserving printer.
- So the AST fully round-trips to source: every node knows the exact token span it came from, and the
  original token stream (incl. all trivia) is retained separately.

## 2. Format-preserving pretty printer (the core)

Entry point `printFormatPreserving($newStmts, $origStmts, $origTokens)`
(`PrettyPrinterAbstract.php:560`). Prerequisites (`:551-554`, and docs
`doc/component/Pretty_printing.markdown:92-109`):
 1. token positions enabled (automatic in v5);
 2. run `NodeVisitor\CloningVisitor` on the AST **before** mutating it;
 3. pass the original tokens.

`CloningVisitor` deep-clones every node and stashes a link back to the untouched original as the
`origNode` attribute (`lib/PhpParser/NodeVisitor/CloningVisitor.php` — `$node->setAttribute('origNode', $origNode)`).
That `origNode` link is the whole trick: the printer compares each new node to its original by identity.

Setup wraps the tokens in a `TokenStream` (`:571`, `lib/PhpParser/Internal/TokenStream.php`) which
precomputes an indentation map per token (`calcIndentMap`, `TokenStream.php:251`) and can emit the
verbatim source for any token range via `getTokenCode(from, to, indentAdjustment)` (`:223`).

### Per-node algorithm — `p()` (`:604`)
1. No `origTokens` ⇒ plain pretty print (`:609`).
2. **No `origNode`** (a newly created node, never cloned from source) ⇒ `pFallback()` = the standard
   per-node printer `p{Type}()` (`:588-590`, `:615-617`). This is how inserted subtrees get formatted.
3. Otherwise walk the node's subnodes (`getSubNodeNames()`, `:649`). Track a cursor `$pos` in the
   original token stream, starting at the node's `startTokenPos`. For each subnode:
   - **Unchanged scalar/subnode** (`$subNode === $origSubNode`) ⇒ skip; its tokens will be copied
     verbatim as part of the surrounding gap (`:656-659`).
   - **Changed array subnode** ⇒ delegate to `pArray()` (`:663`).
   - **Changed child node**: copy the original tokens from `$pos` up to the child's original
     `startTokenPos` verbatim (`getTokenCode`, `:739`), then recurse into `p($subNode, …)` to print the
     child, then advance `$pos` past the child's original `endTokenPos` (`:765`).
   - Inserted (orig was null), removed (new is null), and modifier changes are handled via lookup
     tables (see below).
   After the loop, copy the trailing original tokens up to the node's `endTokenPos` (`:768`).

The key consequence: **a node reprints verbatim from source unless one of its own subnodes changed**;
only the changed child is recursed into, and everything between children is byte-copied from the
original token stream. Formatting (spaces, newlines, comments inside gaps) is thus preserved for free.
Indentation of copied code is shifted by `indentAdjustment` (`:642`) when the node moved to a different
nesting depth.

### List diffing — `pArray()` (`:785`)
Node lists (stmts, params, args, array items, …) are diffed with a **Myers diff**
(`lib/PhpParser/Internal/Differ.php`, `diffWithReplacements` coalesces remove+add into REPLACE,
`Differ.php:53`). The equality callback compares by the `origNode` link:
`$a === $b->getAttribute('origNode')` (`PrettyPrinterAbstract.php:1330-1336`). So a cloned-but-unmoved
element is "KEEP"; new elements are "ADD"; dropped ones "REMOVE".
- **KEEP/REPLACE**: copy the gap tokens before the element verbatim, then print the element
  (recursing) (`:827-906`).
- **ADD**: needs a `listInsertionMap[$parentClass->$subNode]` entry giving the separator, e.g.
  `Expr\Array_->items => ', '`, statement lists => `"\n"` (`:1532-1629`). Goes multiline only if the
  original list was already multiline, the new item has a comment, or it's a match arm
  (`isMultiline()`, `:921-927`, `:1272`).
- **REMOVE**: skips the removed element's token span.
- Returns `null` (⇒ caller falls back to full pretty print of the parent) when it cannot safely
  preserve — e.g. adding a statement to a brace-less single statement (`:806-817`), removing/adding
  around PHP open/close tags (`:863-978`), or a list type with no insertion info (`:908-911`).

### Fixups — `pFixup()` (`:1063`) + `fixupMap` (`:1350`)
When a changed child lands in a position that is precedence/semantics-sensitive (binary-op operands,
call/deref LHS, `new`/`instanceof` operand, braced names, encapsed parts), the printer conditionally
adds parens/braces — but only if the original tokens didn't already have them
(`origTokens->haveParens/haveBraces`, `TokenStream.php:34-49`). Same-identity children skip fixup
(`:750-751`). This keeps semantics correct without gratuitously adding parens.

### Insertion / removal / modifier tables
- `removalMap` (`:1434`): which adjacent tokens to also strip when a subnode becomes null
  (e.g. `Param->type` strips trailing whitespace, `Stmt_Return->expr` strips both sides).
- `insertionMap` (`:1482`): where and with what glue to insert a previously-absent subnode
  (e.g. `Stmt_Function->returnType => [')', false, ': ', null]`).
- `emptyListInsertionMap` (`:1632`): inserting the first element into an empty list (adds `()`, ` implements `, …).
- `modifierChangeMap` (`:1700`): flags/static changes reprint just the modifier run
  (e.g. `ClassMethod->flags` via `pModifiers`, skipping to `T_FUNCTION`).

Whenever no table entry / no safe strategy exists, the code returns `pFallback()` / `null` and the
whole node (or parent) is reprinted with the standard printer. **This is the key limitation: any
unhandled change escalates to a full reprint of that subtree, losing its internal formatting.**

## 3. Comments & docblocks

- Comments are `PhpParser\Comment` / `Comment\Doc` objects with their own
  `startTokenPos/endTokenPos/startFilePos` (`lib/PhpParser/Comment.php:9`, `:69`, `:97`).
- Attached to the *following* node's `comments` attribute by `CommentAnnotatingVisitor`
  (`lib/PhpParser/NodeVisitor/CommentAnnotatingVisitor.php`), which the parser runs automatically
  (`ParserAbstract.php:217`). Access via `getComments()` / `getDocComment()` (`NodeAbstract.php:107,116`).
- In format-preserving mode, if a node's comments are **unchanged**, the printer copies them verbatim
  as part of the gap tokens (comment token span is folded into the node's start via `commentStartPos`,
  `PrettyPrinterAbstract.php:851-903`). If comments changed, it reprints them via `pComments()` which
  calls `Comment::getReformattedText()` (re-indents multi-line comments, normalizes CRLF,
  `Comment.php:120`). Dangling comments with no following node are captured by zero-length `Nop`
  statements (`ParserAbstract.php:955`).

## 4. Newly-inserted / changed nodes

- A node with no `origNode` attribute (freshly constructed) is printed entirely by the **standard
  pretty printer** `p{NodeType}()` via `pFallback()` (`:588`, `:616`). So inserted subtrees get the
  library's canonical style, while their surroundings stay verbatim.
- **Customizable?** Yes. `PrettyPrinter\Standard` (`lib/PhpParser/PrettyPrinter/Standard.php`, one
  `p{Type}` method per node) is designed to be subclassed — the docs explicitly recommend overriding
  the `p{Type}` methods for the node types you care about
  (`doc/component/Pretty_printing.markdown:74-75`). So WE can control the emitted layout of any
  reformatted construct by overriding its method. Some choices are also data-driven via node
  attributes (`kind`, `docLabel`, `shouldPrintRawValue`, …; docs `:34-47`).

## 5. Suitability for our design

**Pros**
- Real, typed AST — trivial to express structural rules (member ordering, import sorting, modifier
  order, use of `[]` vs `array()`) by mutating nodes and re-linking.
- Format preservation gives **localized edits for free**: unmodified nodes are byte-copied from the
  original token stream, exactly our "leave everything else byte-identical" requirement.
- No width/column logic anywhere — the printer never wraps to fit a line length. Multiline vs
  single-line for inserted list items is decided purely by mimicking the original
  (`isMultiline`, `:1272`), which matches our "no width-based rules / preserve the author's form" goal.
- Battle-tested: Rector and PHPStan build on it; BSD-3, active maintenance.

**Fit with "only reformat when the current form is not allowed" (permissive)**
- The engine's contract is: *touch a node only if you change its AST*. If our rule finds a node
  already conforming, we simply **don't mutate it** → its `origNode` identity is preserved → tokens
  copied verbatim → zero change. Only when a node violates a rule do we mutate it, and only that
  subtree reprints. This is exactly the localized-edit model we want, and it comes for free.
- **BUT**: PHP-Parser preserves *structure*, not *whitespace within a single node's own tokens*. The
  gap tokens between subnodes are copied verbatim, but whitespace is not itself modelled as editable
  AST. Pure-whitespace rules (e.g. "space after comma", "no space inside parens", indentation fixes)
  do **not** correspond to any AST mutation — you cannot express "reindent this line" by changing a
  node. To fix such a violation you'd have to force a reprint of the enclosing node (losing its other
  original formatting) or drop below the AST to the token stream. This is the crux: an AST-only engine
  is great for *structural* rules and poor for *whitespace/trivia* rules, which are the bulk of a
  classic coding standard.

**Detecting "user wrote it multi-line vs single-line"**
- Feasible from token positions without reprinting: compare `getStartLine()`/`getEndLine()` of the
  node or of successive list elements, or scan the original token text of the span for a newline. The
  library already does exactly this in `isMultiline()` (`:1272-1296`) by fetching
  `origTokens->getTokenCode(prevEnd, curEnd)` and testing `strpos($text,"\n")`. So a permissive rule
  "allow both `[1,2]` and multiline array, preserve whichever the author used" is directly
  implementable — and in fact preservation is the default behaviour (do nothing → kept verbatim).

**Cons / risks**
- **Whole-subtree reprint on any child change**: if *any* subnode of a node changes and the change
  isn't covered by the insertion/removal/modifier tables, `p()`/`pArray()` bail to `pFallback()` and
  reprint the entire node with canonical style, discarding its internal original formatting
  (`:668`, `:678`, `:704`, `:726`, `pArray` returning `null` at `:815`, `:976`, `:1004`). We must
  keep mutations minimal and localized to avoid collateral reformatting.
- Format preservation is explicitly **best-effort** ("may sometimes reformat more code than
  necessary", docs `:116-117`; many `TODO`s in the maps, e.g. brace insertion `:811-816`).
- Comment re-attachment is heuristic (attached to the following node); moving nodes can move or
  reflow comments.
- Not a whitespace linter: no notion of "column", "space around operator" as fixable units — those
  need a token-layer pass.

**AST-only vs token-only (php-cs-fixer style)**
- Token-only (php-cs-fixer): every rule is a whitespace/token edit; naturally localized and ideal for
  spacing/indent/casing rules, but structural rules (reorder members, group uses) are painful and
  error-prone without a real tree.
- AST-only (PHP-Parser): ideal for structural rules and gives principled localized edits, but cannot
  cleanly express whitespace-only rules and escalates to subtree reprints on unhandled changes.
- **Recommendation**: PHP-Parser is a strong foundation for the *structural* half of the formatter and
  for the localized-edit / permissive-preservation model. For width-free, per-token whitespace rules
  we will likely still need a token-level layer (either php-cs-fixer's tokenizer approach or our own
  pass over `$parser->getTokens()`, which PHP-Parser exposes). A hybrid — PHP-Parser AST for structure
  + a token pass for trivia — is the most promising design; a pure AST-only engine will fight us on
  the many whitespace rules a coding standard needs.

## 6. License & maintenance
- **BSD-3-Clause** (`LICENSE`) — permissive, composer-installable, pure PHP, no binary, no ext beyond
  `ext-tokenizer`/`ext-json` (`composer.json:16-19`).
- v5.x current; commits as recent as 2026-07-11; used by Rector & PHPStan.

## Rector at scale (production evidence)
Rector performs large-scale automated refactoring across entire codebases and relies on exactly this
format-preserving mode: it clones the AST (CloningVisitor), mutates only the nodes a rule matches, and
prints back so untouched code stays byte-identical — the same pipeline documented at
`doc/component/Pretty_printing.markdown:92-109`. That PHP-Parser's preservation printer underpins
Rector (and PHPStan's parsing) is the strongest evidence the approach is production-viable at scale.
