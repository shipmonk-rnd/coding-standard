<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template for a vertical series of statements/members: each on its own line at the
 * given depth, separated by an optional single blank line (author's grouping
 * preserved, runs of 2+ blank lines clamped to 1).
 */
final class StmtSeries
{

    /**
     * @param list<Node> $stmts
     */
    public static function render(array $stmts, int $depth): string
    {
        $out = '';

        foreach ($stmts as $stmt) {
            $blank = $stmt->firstToken()->newlinesBefore() >= 2 ? "\n" : '';
            $out .= "\n" . $blank . Layout::indent($depth) . $stmt->render($depth);
        }

        return $out;
    }

}
