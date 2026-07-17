<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * A statement/member with a trailing same-line comment: `$a = 1; // note`
 * (exactly one space before the comment).
 */
final class TrailingComment implements Node
{

    public function __construct(
        private readonly Node $stmt,
        private readonly SigToken $comment,
    )
    {
    }

    public function render(int $depth): string
    {
        return $this->stmt->render($depth) . ' ' . $this->comment->text;
    }

    public function firstToken(): SigToken
    {
        return $this->stmt->firstToken();
    }

}
