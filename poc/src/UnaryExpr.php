<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: unary operator directly attached to its operand (no whitespace).
 */
final class UnaryExpr implements Node
{

    public function __construct(
        private readonly SigToken $op,
        private readonly Node $operand,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->op);
        $this->operand->render($e, $ctx);
    }

    public function firstToken(): SigToken
    {
        return $this->op;
    }

}
