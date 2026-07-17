<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Parenthesized condition of if/while/for/foreach/switch/match.
 *
 * Allowed forms (matching the existing MultilineConditionSpacing standard, notes/01):
 *
 *   FLAT      `($a && $b)`
 *   BROKEN    `(` newline, condition lines at depth+1 with LEADING operators,
 *             `)` back at the construct's depth:
 *                 if (
 *                     $a
 *                     && $b
 *                 ) {
 *
 * Any newline inside the condition selects the broken form (a half-broken header
 * like `if ($a &&<newline>$b)` is repaired to the canonical broken shape).
 */
final class CondLayout
{

    public static function render(Node $cond, SigToken $close, int $depth): string
    {
        $broken = $cond->firstToken()->newlinesBefore() > 0
            || $close->newlinesBefore() > 0
            || (($cond instanceof BinChain || $cond instanceof Ternary) && $cond->hasJointBreaks());

        if (!$broken) {
            return '(' . $cond->render($depth) . ')';
        }

        $inner = ($cond instanceof BinChain || $cond instanceof Ternary)
            ? $cond->renderAt($depth + 1, $depth + 1)
            : $cond->render($depth + 1);

        return "(\n" . Layout::indent($depth + 1) . $inner . "\n" . Layout::indent($depth) . ')';
    }

}
