<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `(int) $x` — exactly one space after the cast
 * (Generic.Formatting.SpaceAfterCast, notes/01). Cast token itself is verbatim.
 */
final class CastExpr implements Node
{

    public function __construct(
        private readonly SigToken $cast,
        private readonly Node $operand,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->cast);
        $e->space();
        $this->operand->render($e, $ctx);
    }

    public function firstToken(): SigToken
    {
        return $this->cast;
    }

}
