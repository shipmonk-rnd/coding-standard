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

    public function render(int $depth): string
    {
        return $this->op->text . $this->operand->render($depth);
    }

    public function firstToken(): SigToken
    {
        return $this->op;
    }

}
