<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `[modifiers] [type] [&][...]$var [= default]` — single spaces between
 * parts, `&`/`...` attached to the variable.
 */
final class Param implements ListItem
{

    /**
     * @param list<SigToken> $modifiers promoted-property modifiers
     * @param list<AttrGroup> $attrGroups inline attributes (`#[Attr] int $x`)
     */
    public function __construct(
        private readonly array $modifiers,
        private readonly ?TypeNode $type,
        private readonly ?SigToken $byRef,
        private readonly ?SigToken $variadic,
        private readonly SigToken $var,
        private readonly ?Node $default,
        private readonly ?SigToken $trailingComment = null,
        private readonly array $attrGroups = [],
    )
    {
    }

    public function render(int $depth): string
    {
        $out = '';

        foreach ($this->attrGroups as $group) {
            $out .= $group->render($depth) . ' ';
        }

        foreach ($this->modifiers as $modifier) {
            $out .= $modifier->text . ' ';
        }

        if ($this->type !== null) {
            $out .= $this->type->render($depth) . ' ';
        }

        $out .= ($this->byRef !== null ? '&' : '')
            . ($this->variadic !== null ? '...' : '')
            . $this->var->text;

        if ($this->default !== null) {
            $out .= ' = ' . $this->default->render($depth);
        }

        return $out;
    }

    public function firstToken(): SigToken
    {
        if ($this->attrGroups !== []) {
            return $this->attrGroups[0]->firstToken();
        }

        return $this->modifiers[0]
            ?? $this->type?->firstToken()
            ?? $this->byRef
            ?? $this->variadic
            ?? $this->var;
    }

    public function trailingComment(): ?SigToken
    {
        return $this->trailingComment;
    }

}
