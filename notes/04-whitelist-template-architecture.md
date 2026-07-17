# Whitelist / template-matching architecture (user direction, 2026-07-17)

## The problem with the rule-based model (`30` v1)
Rules blacklist bad patterns; anything no rule looks at is silently allowed. Coverage is
unknowable — a missed rule = silently accepted garbage. User wants the inverse:
**whitelist the allowed layouts; everything else is either repaired to the closest
allowed form or (rarely, ideally never) a won't-fix fatal error.** Default-deny.

## The formalization: layout templates = Doc IR run backwards

A Wadler/Prettier **Doc** for a node (groups, soft-lines, fills) denotes a **SET of
layouts** — one per assignment of flat/broken to each choice point. The width-based
printer (rejected in `02`) is merely one *selector* over that set. We reuse the same IR
as a **matcher**:

- **Template** (per construct): fixed tokens + mandated horizontal whitespace (e.g.
  exactly `", "` when flat) + choice points (group: flat|broken) + fill zones (elements
  may be freely grouped into rows) + blank-line-allowed markers + cross-choice constraints
  (trailing comma ⇔ enclosing group broken; closing bracket on own line ⇔ broken; broken
  lines indented exactly depth+1).
- **Match**: does SOME choice-point assignment reproduce the node's actual tokens+trivia
  byte-exactly? Yes → code is in the allowed set → **no-op** (byte-identical output).
- **Repair**: no assignment matches → choose the assignment **closest to the source**
  (preserve author breaks at allowed break points, snap horizontal whitespace, move
  disallowed breaks to nearest allowed point; minimal edit over choice variables), print it.
- **Fatal (won't fix)**: node kind has no template, or content (usually a comment) sits
  where no template allows → loud error. A GAP IS A BUG, NEVER A SILENT ALLOW.

### Totality = the whitelist guarantee
The engine must consume **every token of the file** through some template (root template
= file; statements compose child templates). Anything unconsumed → fatal. Coverage is
mechanically checkable (every parser production needs a template) and fuzzable: run over
a large corpus; every node must match, repair, or fatal — "no rule happened to look" is
impossible by construction.

### The user's array examples = ONE template
`'[' fill(elem, sep=(", " | row-break), blank-line-between-rows-allowed) (',' iff broken) ']'`
- `[1, 2]` → all-flat assignment
- one-per-line, rows of 2, rows+blank-line-groups → other assignments of the same template
- `[1 ,2]` / wrong indent / missing trailing comma → matches nothing → repaired to the
  nearest assignment.

## Consequences for the engine (`30` updated mentally; supersedes rule contract there)
- Keep: tokenizer, CST-lite structural links (templates must know which construct they're
  matching), bracket disambiguation, byte-exact rendering, re-tokenize verification.
- Replace: "N independent fixers + priority/convergence loop" → **one generic
  match/repair engine + N declarative templates**. Idempotency becomes structural:
  repair output IS a template assignment, so it matches on the next run. Fixed point in
  one pass (comments permitting).
- "Closest form" is ONE engine mechanism (metric over choice assignments), not a
  per-rule heuristic.

## Honest risk assessment
1. **Comments** are the main fatal source: they may appear between any two tokens.
   Templates must declare comment-attachment points (joints where a comment is allowed,
   with defined spacing); placements outside those → fatal or conservative give-up
   (leave node verbatim + report). This is where "ideally never happens" gets tested.
2. **Novelty**: no researched tool runs Doc-as-matcher-with-nearest-repair. Topiary
   (declarative CST queries) and PrettyPHP's allow-break index are partial precedents;
   Doc IR expressiveness is proven (Prettier/Biome/mago). The new kernel is small and
   isolated → validate FIRST in the proof-of-concept.
3. **Matching cost**: choice assignments are exponential in theory; in practice
   groups/fills resolve independently/greedily left-to-right (flat vs broken is
   observable directly from "is there a newline in this span"), so matching is linear-ish.
   The observation "read the break state off the source" is exactly dprint/ruff's
   detection trick (`20` §3) reused as a decision procedure.
4. **Template language design** is now the central design artifact (subsumes D6 config):
   its primitives ARE the standard's vocabulary (mandated space, allowed break, fill,
   blank-line marker, iff-broken constraints, indent function).

## PoC reshaped
Implement: tokenizer + CST-lite + template kernel (match/repair/fatal) + array template +
statement/indent template + verification gate; run against `01` golden fixtures AND the
user's 4-form array examples; fuzz idempotency (format twice = identical) and totality on
a real codebase (report fatals, measure how rare).
