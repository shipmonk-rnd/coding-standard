<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function count;

/**
 * Blank-line policy for the breaks inside a class-like body (class / interface /
 * trait / enum / anonymous class) — the one place vertical spacing is MANDATED
 * rather than left to the author (notes/60: structural, kind-based, never width).
 *
 * Corpus-grounded (monorepo backend/src, 2026-07-17):
 *   - blank line after `{` (before the first member): 18 073 classes, 0 without;
 *   - blank line before `}` (after the last member): 18 093 with, 28 without;
 *   - a method is always separated from its neighbours by a blank line;
 *   - consecutive fields (properties / constants / enum cases) are the author's
 *     choice (813 adjacent consts, 974 adjacent cases with NO blank in between).
 *
 * A member's leading docblock/own-line comments are part of that member: the
 * mandated blank goes BEFORE the comment block, never between it and the method
 * it documents (which the tokenizer parses as separate CommentStmt members).
 */
final class MemberSpacing
{

    /**
     * The blank-line policy for the break BEFORE `$members[$i]`:
     *   true  = a blank line is mandatory (inserted if the source lacks it)
     *   false = a blank line is forbidden
     *   null  = author's choice (0-1 preserved, 2+ clamped to 1)
     *
     * @param list<Node> $members
     */
    public static function blankBefore(array $members, int $i): ?bool
    {
        if ($i === 0) {
            return true; // blank line after the opening brace
        }

        // a comment leads the member below it (docblock) — glued, author's choice
        if ($members[$i - 1] instanceof CommentStmt) {
            return null;
        }

        // a blank surrounds every method (and its leading comment block)
        return self::startsMethodUnit($members, $i) || self::isMethod($members[$i - 1]) ? true : null;
    }

    /** Does the member at $i — skipping a leading comment run — declare a method? */
    private static function startsMethodUnit(array $members, int $i): bool
    {
        $n = count($members);

        while ($i < $n && $members[$i] instanceof CommentStmt) {
            $i++;
        }

        return $i < $n && self::isMethod($members[$i]);
    }

    private static function isMethod(Node $node): bool
    {
        if ($node instanceof AttributedNode) {
            $node = $node->target();
        }

        return $node instanceof FunctionDecl;
    }

    /**
     * The break before a class-like `}`: a blank line is mandatory when the body
     * has members (an empty body keeps `{\n}` / `{\n\n}` as the author's choice).
     *
     * @param list<Node> $members
     */
    public static function renderClose(Emitter $e, int $indent, array $members, SigToken $close): void
    {
        $sourceBlank = $close->newlinesBefore() >= 2;
        $mustBlank = $members !== [];
        $breakStart = $e->offset();

        $e->newline($indent, $mustBlank || $sourceBlank);

        if ($mustBlank && !$sourceBlank) {
            $e->violation(new Violation($close->line, 'blank-line spacing does not match the required layout', $breakStart));
        }
    }

}
