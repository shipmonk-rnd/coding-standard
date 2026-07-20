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
     * @param (callable(list<Node>, int): ?bool)|null $blankBefore per-boundary
     *        blank-line policy (true = mandate a blank, false = forbid, null =
     *        author's choice), called with the full series and the index of the
     *        upcoming statement. Omitted for blocks/switch/file series, where blank
     *        lines stay the author's choice.
     */
    public static function render(Emitter $e, array $stmts, int $depth, ?SigToken $boundary, ?callable $blankBefore = null): void
    {
        foreach ($stmts as $i => $stmt) {
            $first = $stmt->firstToken();
            $policy = $blankBefore !== null ? $blankBefore($stmts, $i) : null;
            $breakStart = $e->offset();

            if ($stmt instanceof VerbatimStmt) {
                // keep the frozen statement's original own-line indentation
                $e->newline(0, $policy ?? ($first->newlinesBefore() >= 2));
            } elseif ($policy !== null) {
                $e->newline($depth, $policy);
            } else {
                $e->lineBreak($first, $depth);
            }

            // a mandated/forbidden blank the source didn't already match is a repair,
            // pinned to this member's line (the boundary lives between two slices, so
            // the content comparison below never sees it)
            if ($policy !== null && $policy !== ($first->newlinesBefore() >= 2)) {
                $e->violation(new Violation($first->line, 'blank-line spacing does not match the required layout', $breakStart));
            }

            $outStart = $e->offset();
            $stmt->render($e, RenderCtx::atLine($depth));
            $e->flushTrailing();

            $next = ($stmts[$i + 1] ?? null)?->firstToken() ?? $boundary;

            if ($next === null || $stmt instanceof VerbatimStmt || $e->hasViolationSince($outStart)) {
                continue;
            }

            $srcStart = $first->pos;
            $srcEnd = $next->pos - strlen($next->gapBefore);

            if (substr($e->source, $srcStart, $srcEnd - $srcStart) !== $e->slice($outStart)) {
                $e->violation(new Violation($first->line, 'formatting does not match any allowed form', $outStart));
            }
        }
    }

}
