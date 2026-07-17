<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: expression + `;` with no whitespace before the semicolon.
 */
final class ExprStmt implements Node
{

    public function __construct(
        private readonly Node $expr,
        private readonly SigToken $semi,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $this->expr->render($e, $ctx);
        $e->token($this->semi);
    }

    public function firstToken(): SigToken
    {
        return $this->expr->firstToken();
    }

}
