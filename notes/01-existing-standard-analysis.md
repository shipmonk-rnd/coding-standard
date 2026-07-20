# Analysis of the existing shipmonk/coding-standard

Repo: `repos/coding-standard`. It is a **thin curation layer**, not an engine:
- `ShipMonkCodingStandard/ruleset.xml` (464 lines) selects ~150 rules from bundled
  standards (Generic / PEAR / PSR1 / PSR2 / PSR12 / Squiz — all shipped by
  `squizlabs/php_codesniffer`) plus a large set of `SlevomatCodingStandard.*` rules.
- 7 custom sniffs live in `ShipMonkCodingStandard/Sniffs/`.
- Everything runs on the **PHP_CodeSniffer engine** (token stream + `Sniff` API + `Fixer`).

So "rewrite from scratch without phpcs/slevomat" means: **build our own engine AND
reimplement the subset of rules we want to keep.** The ruleset.xml is the spec of intent.

## The current engine model (what we're replacing)

phpcs is **token-stream based**. A sniff declares token types via `register()`, and
`process()` is called at each matching token; fixes mutate tokens via a `Fixer`
(`replaceToken`, `addContent`, etc.). Example — `OpenParenthesisSpacingSniff`:

```php
public function register(): array { return [T_IF, T_ELSEIF, T_CATCH]; }
public function process(File $file, $ptr): void {
    $opener = $tokens[$ptr]['parenthesis_opener'];
    $next = $tokens[$opener + 1];
    if ($next['code'] !== T_WHITESPACE || $next['content'] === "\n") return;
    $fix = $file->addFixableError('Expected 0 spaces after opening bracket', ...);
    if ($fix) $file->fixer->replaceToken($opener + 1, ltrim($next['content'], " \t"));
}
```

KEY OBSERVATION: this model is **already localized-edit and preservation-friendly**. It
strips only horizontal whitespace and deliberately *preserves* a following newline. phpcs
does NOT reprint the file; each sniff touches only the tokens it cares about. So the
"only reformat what's wrong, leave the rest alone" behavior we want is *native* to the
token model — it's what phpcs already does. The pain with phpcs/slevomat isn't the
approach, it's: heavyweight/awkward sniff API, XML config, slow, token metadata quirks,
no real tree, hard to write cross-cutting rules, and the dependency we want to drop.

This is a strong data point for the AST-vs-token question (note 20): the incumbent is
token-based and its localized-edit behavior is exactly our goal.

## Rule taxonomy — TWO very different concerns are bundled together

The ruleset mixes two fundamentally different kinds of rule. The rewrite MUST decide
scope for each; the "permissive formatter" design only applies to the first kind.

### A. Formatting / whitespace / layout rules (the "permissive formatter" target)
Whitespace, indentation, spacing, blank lines, line breaks, bracket placement, trailing
commas. Examples from the ruleset:
- Generic.WhiteSpace.ScopeIndent, DisallowTabIndent, IncrementDecrementSpacing
- Generic.Formatting.SpaceAfterCast / SpaceAfterNot / MultipleStatementAlignment
- Generic.Functions.OpeningFunctionBraceBsdAllman
- PSR12.Operators.OperatorSpacing; Squiz.WhiteSpace.* (Operator/Object/Cast/Semicolon/…)
- Squiz.Strings.ConcatenationSpacing; Squiz.Arrays.ArrayBracketSpacing
- Slevomat.Arrays.SingleLineArrayWhitespace / MultiLineArrayEndBracketPlacement /
  TrailingArrayComma; Slevomat.Functions.RequireTrailingCommaIn* (+ onlySingleLine)
- Slevomat.Classes.*Spacing, EmptyLinesAroundClassBraces, MethodSpacing, PropertySpacing
- Slevomat.TypeHints.*Spacing, DNFTypeHintFormat; Namespaces.UseSpacing/NamespaceSpacing
- ShipMonk custom: DoubleArrowSpacing, CatchSpacing, MultilineConditionSpacing,
  MultilineTernary, OpenParenthesisSpacing, DisallowOneLineDocComment
- NOTE the existing standard ALREADY embraces permissiveness in places:
  `DisallowTrailingCommaInCall onlySingleLine=true` + `RequireTrailingCommaInCall` =
  "single-line: no trailing comma; multi-line: require it" — i.e. layout-dependent rules,
  exactly the multi-form idea. `allowMultiLine`/`ignoreNewlines`/`allowMultiline` flags
  recur throughout — the incumbent is already semi-permissive by hand.

### B. Lint / code-quality / static-analysis rules (NOT formatting)
These are about code correctness/style-of-logic, not whitespace. A "permissive formatter"
says nothing about them. Many overlap with what PHPStan already does.
- Unused/import hygiene: Slevomat.Namespaces.UnusedUses, UselessAlias,
  ReferenceUsedNamesOnly, AlphabeticallySortedUses; Variables.UnusedVariable,
  UselessVariable, DuplicateAssignmentToVariable
- Code smells: Slevomat.ControlStructures.UselessTernaryOperator,
  RequireNullCoalesce*, DisallowYodaComparison; Operators.DisallowEqualOperators
- Type hints presence: Slevomat.TypeHints.ParameterTypeHint/ReturnTypeHint/PropertyTypeHint
- Comments/annotations: DocComment* content rules, ForbiddenAnnotations/Comments,
  UselessFunctionDocComment, naming conventions, class structure ordering
- Generic.CodeAnalysis.*, Squiz.PHP.NonExecutableCode, one-class-per-file, etc.

### SCOPING DECISION NEEDED (for the user)
The new tool could be:
1. **Pure formatter** — only concern A. Punt all of concern B to PHPStan (which shipmonk
   already uses heavily and which does B better). Smallest, cleanest, best fits the
   "permissive formatter" vision. Recommended default.
2. **Formatter + linter** — reimplement a curated subset of B too, to remain a drop-in
   replacement for today's standard. Much larger; B rules don't benefit from the
   permissive design and mostly duplicate PHPStan.

My strong lean: **scope the from-scratch tool to concern A (formatting)**, and separately
decide which of the B rules (if any) are worth keeping vs delegating to PHPStan. Confirm
with the user before committing — this decision drives the entire architecture.

## Test data format worth copying
Each sniff has `tests/Data/<Rule>/...` with `X.php` (input) + `X.php.fixed` (expected
output) pairs. This golden-file fixture style is a good, simple regression harness to keep
for the new formatter (input → format → compare to `.fixed`).
