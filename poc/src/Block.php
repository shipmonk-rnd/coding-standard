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
     * @param SigToken|null $headerComment trailing comment on the `{` line — must stay
     *        there: line-targeted directives (`// @phpstan-ignore ...`) break if moved
     */
    public function __construct(
        private readonly SigToken $open,
        private readonly array $stmts,
        private readonly SigToken $close,
        private readonly ?SigToken $headerComment = null,
    )
    {
    }

    public function render(int $depth): string
    {
        $open = '{' . ($this->headerComment !== null ? ' ' . $this->headerComment->text : '');
        $blankBeforeClose = $this->close->newlinesBefore() >= 2 ? "\n" : '';

        if ($this->stmts === []) {
            return $open . $blankBeforeClose . "\n" . Layout::indent($depth) . '}';
        }

        return $open
            . StmtSeries::render($this->stmts, $depth + 1)
            . $blankBeforeClose
            . "\n" . Layout::indent($depth) . '}';
    }

    public function firstToken(): SigToken
    {
        return $this->open;
    }

}
