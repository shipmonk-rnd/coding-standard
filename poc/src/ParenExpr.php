<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `(` expr `)` with no whitespace inside the parentheses.
 * PoC limitation (deliberate, documented): no break points inside grouping
 * parentheses — breaks are repaired onto one line.
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
        $e->token($this->open);
        $this->expr->render($e, $ctx);
        $e->token($this->close);
    }

    public function firstToken(): SigToken
    {
        return $this->open;
    }

}
