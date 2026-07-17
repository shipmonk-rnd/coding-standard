<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `case Name;` / `case Name = value;`
 */
final class EnumCase implements Node
{

    public function __construct(
        private readonly SigToken $keyword,
        private readonly SigToken $name,
        private readonly ?Node $value,
    )
    {
    }

    public function render(int $depth): string
    {
        return $this->keyword->text . ' ' . $this->name->text
            . ($this->value !== null ? ' = ' . $this->value->render($depth) : '')
            . ';';
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
