<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Parenthesized condition of if/while/do-while/switch/match, including an optional
 * trailing comment before the closing paren (`&& !$x->isDirty() // reason`).
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
final class Cond
{

    public function __construct(
        private readonly Node $expr,
        private readonly ?SigToken $trailingComment,
        private readonly SigToken $close,
    )
    {
    }

    public function render(int $depth): string
    {
        $broken = $this->expr->firstToken()->newlinesBefore() > 0
            || $this->close->newlinesBefore() > 0
            || $this->trailingComment !== null
            || (($this->expr instanceof BinChain || $this->expr instanceof Ternary) && $this->expr->hasJointBreaks());

        if (!$broken) {
            return '(' . $this->expr->render($depth) . ')';
        }

        $inner = ($this->expr instanceof BinChain || $this->expr instanceof Ternary)
            ? $this->expr->renderAt($depth + 1, $depth + 1)
            : $this->expr->render($depth + 1);

        return "(\n" . Layout::indent($depth + 1) . $inner
            . ($this->trailingComment !== null ? ' ' . $this->trailingComment->text : '')
            . "\n" . Layout::indent($depth) . ')';
    }

}
