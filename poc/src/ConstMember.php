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

        $e->token($this->keyword);
        $e->space();
        $e->token($this->name);
        $e->text(' = ');
        $this->value->render($e, $ctx);
        $e->token($this->semi);
    }

    public function firstToken(): SigToken
    {
        return $this->modifiers[0] ?? $this->keyword;
    }

}
