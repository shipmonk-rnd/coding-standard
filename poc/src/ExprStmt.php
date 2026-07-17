<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: expression + `;` with no whitespace before the semicolon.
 */
final class ExprStmt implements Node
{

    public function __construct(
        private readonly Node $expr,
    )
    {
    }

    public function render(int $depth): string
    {
        return $this->expr->render($depth) . ';';
    }

    public function firstToken(): SigToken
    {
        return $this->expr->firstToken();
    }

}
