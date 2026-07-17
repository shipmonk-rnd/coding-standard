<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `value` or `key => value` with exactly one space around `=>`;
 * optional trailing same-line comment (rendered by the enclosing collection).
 */
final class ArrayItem implements ListItem
{

    public function __construct(
        private readonly ?Node $key,
        private readonly Node $value,
        private readonly ?SigToken $trailingComment = null,
        private readonly bool $named = false,
    )
    {
    }

    public function render(int $depth): string
    {
        if ($this->key === null) {
            return $this->value->render($depth);
        }

        if ($this->named) {
            return $this->key->render($depth) . ': ' . $this->value->render($depth);
        }

        return $this->key->render($depth) . ' => ' . $this->value->render($depth);
    }

    public function firstToken(): SigToken
    {
        return ($this->key ?? $this->value)->firstToken();
    }

    public function trailingComment(): ?SigToken
    {
        return $this->trailingComment;
    }

}
