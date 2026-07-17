<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `keyword` / `keyword expr` (single space) — clone, yield, yield from,
 * include/require as expressions, print, throw-as-expression.
 */
final class KeywordExpr implements Node
{

    public function __construct(
        private readonly SigToken $keyword,
        private readonly ?Node $expr,
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
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
