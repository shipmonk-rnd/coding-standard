<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `(` expr `)` with no whitespace inside the parentheses.
 * PoC limitation: no break points inside grouping parentheses (breaks are repaired
 * onto one line).
 */
final class ParenExpr implements Node
{

    public function __construct(
        private readonly SigToken $open,
        private readonly Node $expr,
    )
    {
    }

    public function render(int $depth): string
    {
        return '(' . $this->expr->render($depth) . ')';
    }

    public function firstToken(): SigToken
    {
        return $this->open;
    }

}
