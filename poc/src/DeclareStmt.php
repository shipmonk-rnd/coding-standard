<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `declare(strict_types = 1);` — fully mandated horizontal layout
 * (spaces around `=`, no space inside the parentheses), no choice points.
 */
final class DeclareStmt implements Node
{

    public function __construct(
        private readonly SigToken $keyword,
        private readonly SigToken $directive,
        private readonly Node $value,
    )
    {
    }

    public function render(int $depth): string
    {
        return $this->keyword->text . '(' . $this->directive->text . ' = ' . $this->value->render($depth) . ');';
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
