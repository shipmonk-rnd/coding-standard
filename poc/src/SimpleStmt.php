<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `keyword [expr];` — return, throw, break, continue, echo, require/include.
 *
 * Normally flat (`return $x;`). When the keyword carries a trailing comment, or
 * own-line comment rows follow it, or the expression starts on the next line, the
 * expression is pushed onto continuation lines at depth+1 (leading operators), with
 * the comment rows preserved between:
 *     return // why
 *         // more context
 *         $a
 *         || $b;
 */
final class SimpleStmt implements Node
{

    /**
     * @param list<SigToken> $leading own-line comment rows between the keyword and expr
     */
    public function __construct(
        private readonly SigToken $keyword,
        private readonly array $leading,
        private readonly ?Node $expr,
        private readonly SigToken $semi,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->keyword);

        if ($this->expr !== null) {
            $broken = $this->keyword->trailingComment !== null
                || $this->leading !== []
                || $this->expr->firstToken()->newlinesBefore() > 0;

            if ($broken) {
                foreach ($this->leading as $comment) {
                    $e->lineBreak($comment, $ctx->cont);
                    CommentStmt::emitReindented($e, $comment, $ctx->cont);
                }

                $e->newline($ctx->cont);
                $this->expr->render($e, RenderCtx::aligned($ctx->cont));
            } else {
                $e->space();
                $this->expr->render($e, $ctx);
            }
        }

        $e->token($this->semi);
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
