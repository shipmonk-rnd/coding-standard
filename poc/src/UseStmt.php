<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `use X;` / `use function x;` / `use const X;` / `use X as Y;`
 * (also trait use inside a class body). One import per statement.
 */
final class UseStmt implements Node
{

    public function __construct(
        private readonly SigToken $keyword,
        private readonly ?SigToken $kind,
        private readonly SigToken $name,
        private readonly ?SigToken $alias,
    )
    {
    }

    public function render(int $depth): string
    {
        return $this->keyword->text
            . ($this->kind !== null ? ' ' . $this->kind->text : '')
            . ' ' . $this->name->text
            . ($this->alias !== null ? ' as ' . $this->alias->text : '')
            . ';';
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
