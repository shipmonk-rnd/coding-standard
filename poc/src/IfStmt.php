<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `if (cond) { ... } elseif (cond) { ... } else { ... }` — braces
 * mandatory, `} elseif (` / `} else {` on one line, condition layout per Cond.
 */
final class IfStmt implements Node
{

    /**
     * @param list<array{SigToken, Cond, Block}> $elseifs kw, cond, block
     */
    public function __construct(
        private readonly SigToken $keyword,
        private readonly Cond $cond,
        private readonly Block $then,
        private readonly array $elseifs,
        private readonly ?Block $else,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->keyword);
        $e->space();
        $this->cond->render($e, $ctx->line);
        $e->space();
        $this->then->render($e, $ctx);

        foreach ($this->elseifs as [$kw, $cond, $block]) {
            $e->space();
            $e->token($kw);
            $e->space();
            $cond->render($e, $ctx->line);
            $e->space();
            $block->render($e, $ctx);
        }

        if ($this->else !== null) {
            $e->text(' else ');
            $this->else->render($e, $ctx);
        }
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
