<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: ternary `cond ? then : else` (also short `cond ?: else`).
 *
 * Allowed forms (matching the existing MultilineTernary standard):
 *   FLAT      `$cond ? $a : $b`
 *   BROKEN    condition stays on its line, both branch operators LEADING at
 *             $ctx->cont:
 *                 $cond
 *                     ? $a
 *                     : $b
 * Any break in the joints selects the fully broken form.
 */
final class Ternary implements Node
{

    public function __construct(
        private readonly Node $cond,
        private readonly SigToken $question,
        private readonly ?Node $then,
        private readonly SigToken $colon,
        private readonly Node $else,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $this->cond->render($e, $ctx);
        $broken = $this->hasJointBreaks();
        $branchCtx = $broken ? RenderCtx::atLine($ctx->cont) : $ctx;

        if ($this->then === null) {
            $broken ? $e->newline($ctx->cont) : $e->space();
            $e->token($this->question);
            $e->token($this->colon);
            $e->space();
            $this->else->render($e, $branchCtx);

            return;
        }

        $broken ? $e->newline($ctx->cont) : $e->space();
        $e->token($this->question);
        $e->space();
        $this->then->render($e, $branchCtx);
        $broken ? $e->newline($ctx->cont) : $e->space();
        $e->token($this->colon);
        $e->space();
        $this->else->render($e, $branchCtx);
    }

    public function hasJointBreaks(): bool
    {
        return $this->question->newlinesBefore() > 0
            || ($this->then !== null && $this->then->firstToken()->newlinesBefore() > 0)
            || $this->colon->newlinesBefore() > 0
            || $this->else->firstToken()->newlinesBefore() > 0;
    }

    public function firstToken(): SigToken
    {
        return $this->cond->firstToken();
    }

}
