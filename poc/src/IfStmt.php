<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `if (cond) { ... } elseif (cond) { ... } else { ... }` — braces
 * mandatory, `} elseif (` / `} else {` on one line, condition layout per CondLayout.
 */
final class IfStmt implements Node
{

    /**
     * @param list<array{SigToken, Node, SigToken, Block}> $elseifs kw, cond, condClose, block
     */
    public function __construct(
        private readonly SigToken $keyword,
        private readonly Node $cond,
        private readonly SigToken $condClose,
        private readonly Block $then,
        private readonly array $elseifs,
        private readonly ?Block $else,
    )
    {
    }

    public function render(int $depth): string
    {
        $out = $this->keyword->text . ' ' . CondLayout::render($this->cond, $this->condClose, $depth)
            . ' ' . $this->then->render($depth);

        foreach ($this->elseifs as [$kw, $cond, $close, $block]) {
            $out .= ' ' . $kw->text . ' ' . CondLayout::render($cond, $close, $depth) . ' ' . $block->render($depth);
        }

        if ($this->else !== null) {
            $out .= ' else ' . $this->else->render($depth);
        }

        return $out;
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
