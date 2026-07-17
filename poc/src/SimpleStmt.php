<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `keyword [expr];` — return, throw, break, continue, echo, require/include.
 */
final class SimpleStmt implements Node
{

    public function __construct(
        private readonly SigToken $keyword,
        private readonly ?Node $expr,
    )
    {
    }

    public function render(int $depth): string
    {
        return $this->keyword->text
            . ($this->expr !== null ? ' ' . $this->expr->render($depth) : '')
            . ';';
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
