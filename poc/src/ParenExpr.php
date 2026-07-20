<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: grouping parentheses.
 *
 * Allowed forms (mirrors Cond):
 *   FLAT      `($a && $b)` — no whitespace inside the parentheses
 *   BROKEN    `(` newline, inner expression at the anchor's depth + 1 with leading
 *             operators, `)` back at the anchor's depth:
 *                 && !( // trailing comments ride as `(` trivia
 *                     $a
 *                     && $b
 *                 )
 */
final class ParenExpr implements Node
{

    public function __construct(
        private readonly SigToken $open,
        private readonly Node $expr,
        private readonly SigToken $close,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $broken = $this->expr->firstToken()->newlinesBefore() > 0
            || $this->close->newlinesBefore() > 0
            || (($this->expr instanceof BinChain || $this->expr instanceof Ternary) && $this->expr->hasJointBreaks());

        $e->token($this->open);

        if (!$broken) {
            $this->expr->render($e, $ctx);
            $e->token($this->close);

            return;
        }

        $e->newline($ctx->line + 1);
        $this->expr->render($e, RenderCtx::aligned($ctx->line + 1));
        $e->newline($ctx->line);
        $e->token($this->close);
    }

    public function firstToken(): SigToken
    {
        return $this->open;
    }

}
