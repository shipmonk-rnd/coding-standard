<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: match is ALWAYS broken (one arm per line, trailing comma) — the single
 * deliberately canonical-vertical construct (a flat match has no allowed form).
 *
 *     match (subject) {
 *         cond1, cond2 => expr,
 *         default => expr,
 *     }
 */
final class MatchExpr implements Node
{

    /**
     * @param list<MatchArm|CommentRow> $arms
     */
    public function __construct(
        private readonly SigToken $keyword,
        private readonly Cond $subject,
        private readonly SigToken $braceOpen,
        private readonly array $arms,
        private readonly SigToken $braceClose,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->keyword);
        $e->space();
        $this->subject->render($e, $ctx->line);
        $e->space();
        $e->token($this->braceOpen);

        foreach ($this->arms as $i => $arm) {
            $e->newline($ctx->line + 1, $i > 0 && $arm->firstToken()->newlinesBefore() >= 2);
            $arm->render($e, RenderCtx::atLine($ctx->line + 1));
        }

        $e->newline($ctx->line);
        $e->token($this->braceClose);
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
