# Open decisions (to settle before writing engine code)

Ordered by how much they constrain everything downstream.

> **RESOLVED 2026-07-17:** D1 = **pure formatter** (delegate lint to PHPStan).
> D2/D3 = **pure PHP / composer, no binary** (rules out mago). D4 = **no width-based
> rules** (see `02`) → drops the Wadler Doc engine; shifts to tree/token localized-edit
> model. D5 = **token-based "CST-lite"** engine on the native tokenizer (whitespace/comments
> as first-class tokens + precomputed structural links), NO AST, NO parser dependency (see
> `30`). This also answers the parser-dependency question: none needed — native tokenizer
> only. Core framing (`03`): permissiveness is a narrow carve-out (vertical arrangement
> only); comma placement, indentation, spacing are all strictly enforced.
> Remaining open: D6 (config schema), D7 (per-construct allowed-form set + structural
> triggers), and a proof-of-concept to validate the engine.

## D1. Scope — pure formatter, or formatter + linter? (see `01` §taxonomy)  ✅ pure formatter
Today's standard bundles (A) formatting/whitespace rules and (B) lint/code-quality rules
(unused imports, useless vars, type-hint presence, forbidden annotations, naming…). The
"permissive / multiple-allowed-forms" design only applies to (A). Most of (B) overlaps
with PHPStan, which shipmonk already runs.
- **Option**: pure formatter (A only), delegate B to PHPStan. ← my lean
- **Option**: formatter + curated linter (A + subset of B), drop-in replacement.

## D2. Language / engine base (see `20` §5) — the pivotal call
- **Build on mago (Rust)** — fork its PHP parser + Doc formatter, flip preserve defaults
  permissive, add violation-scoping + verification. Fast, high-quality, ~70-80% done.
  Distribution = binary (mago already solves this w/ composer bridge). ← my lean unless D3
  says otherwise.
- **Build in PHP** — composer-native, contributor-friendly; but rebuild parser-fidelity +
  Doc engine + preservation; slower.
- **New Rust engine, reuse only mago's parser.**

## D3. Distribution / audience constraint (feeds D2)
Is "pure `composer require`, no compiled binary, hackable by PHP devs" a HARD requirement
for a shipmonk OSS PHP coding standard? If yes → pushes D2 toward PHP. If a binary (à la
mago / php-cs-fixer phar / rust tools) is acceptable → Rust is on the table.

## D4. Printing strategy (see `20` §3)
- **(C) preserve-first, violation-scoped + verification gate** ← recommended
- (A) full preserve-first reprint
- (B) localized token edits only
Depends partly on D2 (C is easiest on a lossless-CST/range-splice-capable base).

## D5. Data-model flavor (if not inherited from mago)
Lossless CST (biome-style) vs AST + trivia buffer + source rescanning (mago/ruff/oxc).
mago already picked AST+trivia; if we build on it we inherit that.

## D6. How "allowed forms" are specified in config
Per-construct "these forms are allowed" + a "preferred form used only when reformatting is
forced". Need a config schema expressing this (generalization of mago's `preserve_*` +
oxc's `Expand::{Auto,Always,Never}`). Design after D1/D2.

## D7. The canonical multi-form set for each construct
Concretely enumerate, per PHP construct (arrays, call args, param lists, match arms, chained
methods, binary/ternary chains, use lists, attributes, …), which layouts are "allowed" and
which is "preferred". This is the actual *coding standard content* and can be drafted in
parallel with engine decisions. The existing ruleset (`01`) + shipmonk code samples are the
source of truth for intent.
