<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Braced statement block. `{` stays on the caller's line; statements at line+1;
 * `}` on its own line at the caller's line indent. A single blank line directly
 * after `{` or before `}` is allowed and preserved (deliberate, notes/01).
 * Comments on the `{` line ride as trivia and are flushed by the first break.
 */
final class Block implements Node
{

    /**
     * @param list<Node> $stmts
     */
    public function __construct(
        private readonly SigToken $open,
        private readonly array $stmts,
        private readonly SigToken $close,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->open);
        StmtSeries::render($e, $this->stmts, $ctx->line + 1, $this->close);
        $e->newline($ctx->line, $this->close->newlinesBefore() >= 2);
        $e->token($this->close);
    }

    public function firstToken(): SigToken
    {
        return $this->open;
    }

}
