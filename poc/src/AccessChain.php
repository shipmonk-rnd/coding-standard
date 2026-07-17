<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: base + postfix segments.
 *
 * Allowed forms per `->` / `?->` joint (author's choice):
 *   FLAT      attached directly
 *   BROKEN    newline, operator LEADING on the continuation line at depth+1:
 *                 $query
 *                     ->from($table)
 *                     ->where($cond)
 * `::`, `[...]`, `(...)`, `++/--` never break (repaired flat).
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

    public function render(int $depth): string
    {
        $out = $this->base->render($depth);
        $current = $depth;

        foreach ($this->segments as $segment) {
            if ($segment->isObjectOp() && $segment->op->newlinesBefore() > 0) {
                $current = $depth + 1;
                $out .= "\n" . Layout::indent($current);
            }

            $out .= $segment->render($current);
        }

        return $out;
    }

    public function firstToken(): SigToken
    {
        return $this->base->firstToken();
    }

}
