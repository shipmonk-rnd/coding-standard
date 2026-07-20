<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `[#[Attr] ][modifiers ][type ][&][...]$var[ = default]` — single spaces
 * between parts, `&`/`...` attached to the variable.
 */
final class Param implements ListItem
{

    /**
     * @param list<AttrGroup> $attrGroups inline attributes (`#[Attr] int $x`)
     * @param list<SigToken> $modifiers promoted-property modifiers
     */
    public function __construct(
        private readonly array $attrGroups,
        private readonly array $modifiers,
        private readonly ?TypeNode $type,
        private readonly ?SigToken $byRef,
        private readonly ?SigToken $variadic,
        private readonly SigToken $var,
        private readonly ?Node $default,
        private readonly ?SigToken $comma,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        // attribute groups: inline (`#[Attr] int $x`) or on their own line above the
        // parameter — author's choice, read off the source
        foreach ($this->attrGroups as $i => $group) {
            $group->render($e, $ctx);
            $next = ($this->attrGroups[$i + 1] ?? null)?->firstToken() ?? $this->afterAttrsToken();

            if ($next->newlinesBefore() > 0) {
                $e->newline($ctx->line);
            } else {
                $e->space();
            }
        }

        foreach ($this->modifiers as $modifier) {
            $e->token($modifier);
            $e->space();
        }

        if ($this->type !== null) {
            $this->type->render($e, $ctx);
            $e->space();
        }

        if ($this->byRef !== null) {
            $e->token($this->byRef);
        }

        if ($this->variadic !== null) {
            $e->token($this->variadic);
        }

        $e->token($this->var);

        if ($this->default !== null) {
            $e->text(' = ');
            $this->default->render($e, $ctx);
        }
    }

    public function firstToken(): SigToken
    {
        if ($this->attrGroups !== []) {
            return $this->attrGroups[0]->firstToken();
        }

        return $this->afterAttrsToken();
    }

    private function afterAttrsToken(): SigToken
    {
        return $this->modifiers[0]
            ?? $this->type?->firstToken()
            ?? $this->byRef
            ?? $this->variadic
            ?? $this->var;
    }

    public function commaToken(): ?SigToken
    {
        return $this->comma;
    }

}
