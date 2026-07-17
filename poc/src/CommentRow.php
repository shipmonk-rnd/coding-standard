<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * A comment forming its own row inside a broken collection or match body.
 * Emitted verbatim; its presence forces the collection broken.
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
        $e->token($this->token);
    }

    public function firstToken(): SigToken
    {
        return $this->token;
    }

}
