<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `private ?Foo $x = default;` — one property per declaration
 * (matches DisallowMultiPropertyDefinition, notes/01).
 */
final class PropertyMember implements Node
{

    /**
     * @param list<SigToken> $modifiers
     */
    public function __construct(
        private readonly array $modifiers,
        private readonly ?TypeNode $type,
        private readonly SigToken $var,
        private readonly ?Node $default,
    )
    {
    }

    public function render(int $depth): string
    {
        $out = '';

        foreach ($this->modifiers as $modifier) {
            $out .= $modifier->text . ' ';
        }

        if ($this->type !== null) {
            $out .= $this->type->render($depth) . ' ';
        }

        $out .= $this->var->text;

        if ($this->default !== null) {
            $out .= ' = ' . $this->default->render($depth);
        }

        return $out . ';';
    }

    public function firstToken(): SigToken
    {
        return $this->modifiers[0] ?? $this->type?->firstToken() ?? $this->var;
    }

}
