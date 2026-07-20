<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `do { ... } while (cond);`
 */
final class DoWhileStmt implements Node
{

    public function __construct(
        private readonly SigToken $keyword,
        private readonly Block $block,
        private readonly Cond $cond,
        private readonly SigToken $semi,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->keyword);
        $e->space();
        $this->block->render($e, $ctx);
        $e->text(' while ');
        $this->cond->render($e, $ctx->line);
        $e->token($this->semi);
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
