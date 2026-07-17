<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Braced statement block. `{` stays on the caller's line; statements at depth+1;
 * `}` on its own line at the caller's depth. A single blank line directly after `{`
 * or before `}` is allowed and preserved (useful in try/catch blocks — the existing
 * standard deliberately allows this, see notes/01).
 */
final class Block implements Node
{

    /**
     * @param list<Node> $stmts
     */
    public function __construct(
        private readonly SigToken $open,
        private readonly array $stmts,
        private readonly SigToken $close,
    )
    {
    }

    public function render(int $depth): string
    {
        $blankBeforeClose = $this->close->newlinesBefore() >= 2 ? "\n" : '';

        if ($this->stmts === []) {
            return '{' . $blankBeforeClose . "\n" . Layout::indent($depth) . '}';
        }

        return '{'
            . StmtSeries::render($this->stmts, $depth + 1)
            . $blankBeforeClose
            . "\n" . Layout::indent($depth) . '}';
    }

    public function firstToken(): SigToken
    {
        return $this->open;
    }

}
