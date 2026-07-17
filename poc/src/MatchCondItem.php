<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * One condition of a match arm's condition list (`cond1, cond2 => expr`). Wraps the
 * condition expression and its following separator comma so the list can be treated
 * like any other comma-separated collection (rows, blank grouping, trailing-comment
 * trivia) — the sole difference being that match conditions carry NO mandatory
 * trailing comma before `=>`.
 */
final class MatchCondItem implements ListItem
{

    public function __construct(
        private readonly Node $expr,
        private readonly ?SigToken $comma,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $this->expr->render($e, $ctx);
    }

    public function firstToken(): SigToken
    {
        return $this->expr->firstToken();
    }

    public function commaToken(): ?SigToken
    {
        return $this->comma;
    }

}
