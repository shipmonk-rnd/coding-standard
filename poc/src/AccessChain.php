<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: base + postfix segments.
 *
 * Allowed forms per `->` / `?->` joint (author's choice):
 *   FLAT      attached directly
 *   BROKEN    newline, operator LEADING on the continuation line at $ctx->cont:
 *                 $query
 *                     ->from($table)
 *                     ->where($cond)
 * `::`, `[...]`, `(...)`, `++/--` never break (repaired flat). Trailing comments
 * between segments ride as token trivia and are flushed by the joint break.
 */
final class AccessChain implements Node
{

    /**
     * @param list<Segment> $segments
     */
    public function __construct(
        private readonly Node $base,
        private readonly array $segments,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $this->base->render($e, $ctx);
        $current = $ctx;

        foreach ($this->segments as $segment) {
            if ($segment->isObjectOp() && $segment->op->newlinesBefore() > 0) {
                $e->newline($ctx->cont);
                $current = RenderCtx::atLine($ctx->cont);
            }

            $segment->render($e, $current);
        }
    }

    public function firstToken(): SigToken
    {
        return $this->base->firstToken();
    }

}
