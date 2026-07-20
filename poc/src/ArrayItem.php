<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `value`, `key => value` (one space around `=>`) or `name: value`
 * (named argument, no space before `:`).
 */
final class ArrayItem implements ListItem
{

    public function __construct(
        private readonly ?Node $key,
        private readonly ?SigToken $arrow,
        private readonly Node $value,
        private readonly ?SigToken $comma,
        private readonly bool $named = false,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        if ($this->key !== null) {
            $this->key->render($e, $ctx);

            if ($this->named) {
                $e->token($this->arrow); // the `:` of a named argument
                $e->space();
            } else {
                $e->space();
                $e->token($this->arrow);
                $e->space();
            }
        }

        $this->value->render($e, $ctx);
    }

    public function firstToken(): SigToken
    {
        return ($this->key ?? $this->value)->firstToken();
    }

    public function commaToken(): ?SigToken
    {
        return $this->comma;
    }

}
