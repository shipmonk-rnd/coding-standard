<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * A leaf expression token (number, string literal, variable, name) — verbatim.
 */
final class Atom implements Node
{

    public function __construct(
        private readonly SigToken $token,
    )
    {
    }

    public function render(int $depth): string
    {
        return $this->token->text;
    }

    public function firstToken(): SigToken
    {
        return $this->token;
    }

}
