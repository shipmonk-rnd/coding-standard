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
        private readonly SigToken $semi,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        foreach ($this->modifiers as $modifier) {
            $e->token($modifier);
            $e->space();
        }

        if ($this->type !== null) {
            $this->type->render($e, $ctx);
            $e->space();
        }

        $e->token($this->var);

        if ($this->default !== null) {
            $e->text(' = ');
            $this->default->render($e, $ctx);
        }

        $e->token($this->semi);
    }

    public function firstToken(): SigToken
    {
        return $this->modifiers[0] ?? $this->type?->firstToken() ?? $this->var;
    }

}
