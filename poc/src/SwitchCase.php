<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * One `case expr:` / `default:` arm of a switch, statements at +1.
 */
final class SwitchCase
{

    /**
     * @param list<Node> $stmts
     */
    public function __construct(
        private readonly SigToken $keyword,
        private readonly ?Node $expr,
        private readonly array $stmts,
    )
    {
    }

    public function renderCase(int $depth): string
    {
        $label = $this->expr !== null
            ? $this->keyword->text . ' ' . $this->expr->render($depth) . ':'
            : $this->keyword->text . ':';

        return $label . StmtSeries::render($this->stmts, $depth + 1);
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
