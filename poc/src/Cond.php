<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Parenthesized condition of if/while/do-while/switch/match.
 *
 * Allowed forms (matching the existing MultilineConditionSpacing standard, notes/01):
 *
 *   FLAT      `($a && $b)`
 *   BROKEN    `(` newline, condition lines at depth+1 with LEADING operators
 *             aligned under the first operand, `)` back at the construct's depth:
 *                 if (
 *                     $a
 *                     && $b
 *                 ) {
 *
 * Any newline inside the condition selects the broken form. Trailing comments
 * before `)` ride as token trivia, flushed by the closing break.
 */
final class Cond
{

    public function __construct(
        private readonly SigToken $open,
        private readonly Node $expr,
        private readonly SigToken $close,
    )
    {
    }

    public function render(Emitter $e, int $depth): void
    {
        $broken = $this->expr->firstToken()->newlinesBefore() > 0
            || $this->close->newlinesBefore() > 0
            || (($this->expr instanceof BinChain || $this->expr instanceof Ternary) && $this->expr->hasJointBreaks());

        $e->token($this->open);

        if (!$broken) {
            $this->expr->render($e, RenderCtx::atLine($depth));
            $e->token($this->close);

            return;
        }

        $e->newline($depth + 1);
        $this->expr->render($e, RenderCtx::aligned($depth + 1));
        $e->newline($depth);
        $e->token($this->close);
    }

}
