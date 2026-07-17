<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `keyword [expr];` — return, throw, break, continue, echo, require/include.
 */
final class SimpleStmt implements Node
{

    public function __construct(
        private readonly SigToken $keyword,
        private readonly ?Node $expr,
        private readonly SigToken $semi,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->keyword);

        if ($this->expr !== null) {
            $e->space();
            $this->expr->render($e, $ctx);
        }

        $e->token($this->semi);
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
