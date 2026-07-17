<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `private const NAME = value;`
 */
final class ConstMember implements Node
{

    /**
     * @param list<SigToken> $modifiers
     */
    public function __construct(
        private readonly array $modifiers,
        private readonly SigToken $keyword,
        private readonly SigToken $name,
        private readonly Node $value,
    )
    {
    }

    public function render(int $depth): string
    {
        $out = '';

        foreach ($this->modifiers as $modifier) {
            $out .= $modifier->text . ' ';
        }

        return $out . $this->keyword->text . ' ' . $this->name->text . ' = ' . $this->value->render($depth) . ';';
    }

    public function firstToken(): SigToken
    {
        return $this->modifiers[0] ?? $this->keyword;
    }

}
