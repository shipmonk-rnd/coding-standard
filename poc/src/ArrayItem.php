<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `value` or `key => value` with exactly one space around `=>`.
 */
final class ArrayItem implements Node
{

    public function __construct(
        private readonly ?Node $key,
        private readonly Node $value,
    )
    {
    }

    public function render(int $depth): string
    {
        if ($this->key === null) {
            return $this->value->render($depth);
        }

        return $this->key->render($depth) . ' => ' . $this->value->render($depth);
    }

    public function firstToken(): SigToken
    {
        return ($this->key ?? $this->value)->firstToken();
    }

}
