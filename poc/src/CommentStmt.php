<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * A comment on its own line at statement level. Emitted verbatim.
 */
final class CommentStmt implements Node
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
