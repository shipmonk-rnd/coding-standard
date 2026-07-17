<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function strlen;
use function substr;

/**
 * Template for a vertical series of statements/members: each on its own line at the
 * given depth, separated by an optional single blank line (author's grouping
 * preserved via Emitter::lineBreak).
 *
 * Also the per-statement outcome layer (notes/50 §4): when $boundary is known, each
 * statement's output slice is compared to its source slice; a difference records a
 * REPAIR Violation at the statement's line (innermost wins — nested series report
 * first and the outer statement then stays silent).
 */
final class StmtSeries
{

    /**
     * @param list<Node> $stmts
     * @param SigToken|null $boundary the token following the series (block close, EOF)
     */
    public static function render(Emitter $e, array $stmts, int $depth, ?SigToken $boundary): void
    {
        foreach ($stmts as $i => $stmt) {
            if ($stmt instanceof VerbatimStmt) {
                // keep the frozen statement's original own-line indentation
                $e->newline(0, $stmt->firstToken()->newlinesBefore() >= 2);
            } else {
                $e->lineBreak($stmt->firstToken(), $depth);
            }

            $outStart = $e->offset();
            $stmt->render($e, RenderCtx::atLine($depth));
            $e->flushTrailing();

            $next = ($stmts[$i + 1] ?? null)?->firstToken() ?? $boundary;

            if ($next === null || $stmt instanceof VerbatimStmt || $e->hasViolationSince($outStart)) {
                continue;
            }

            $srcStart = $stmt->firstToken()->pos;
            $srcEnd = $next->pos - strlen($next->gapBefore);

            if (substr($e->source, $srcStart, $srcEnd - $srcStart) !== $e->slice($outStart)) {
                $e->violation(new Violation($stmt->firstToken()->line, 'formatting does not match any allowed form', $outStart));
            }
        }
    }

}
