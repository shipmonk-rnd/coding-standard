<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `while (cond) { ... }`
 */
final class WhileStmt implements Node
{

    public function __construct(
        private readonly SigToken $keyword,
        private readonly Cond $cond,
        private readonly Block $block,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->keyword);
        $e->space();
        $this->cond->render($e, $ctx->line);
        $e->space();
        $this->block->render($e, $ctx);
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
