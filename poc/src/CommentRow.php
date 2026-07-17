<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * A comment forming its own row inside a broken collection or match body.
 * Its presence forces the collection broken; docblock continuation lines are
 * re-indented to the row's depth (content untouched).
 */
final class CommentRow implements Node
{

    public function __construct(
        public readonly SigToken $token,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        CommentStmt::emitReindented($e, $this->token, $ctx->line);
    }

    public function firstToken(): SigToken
    {
        return $this->token;
    }

}
