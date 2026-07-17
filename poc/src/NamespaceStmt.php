<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `namespace Foo\Bar;`
 */
final class NamespaceStmt implements Node
{

    public function __construct(
        private readonly SigToken $keyword,
        private readonly SigToken $name,
    )
    {
    }

    public function render(int $depth): string
    {
        return $this->keyword->text . ' ' . $this->name->text . ';';
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
