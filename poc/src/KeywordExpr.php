<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `keyword` / `keyword expr` (single space) — clone, yield, yield from,
 * include/require as expressions, print.
 */
final class KeywordExpr implements Node
{

    public function __construct(
        private readonly SigToken $keyword,
        private readonly ?Node $expr,
    )
    {
    }

    public function render(int $depth): string
    {
        return $this->keyword->text . ($this->expr !== null ? ' ' . $this->expr->render($depth) : '');
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
