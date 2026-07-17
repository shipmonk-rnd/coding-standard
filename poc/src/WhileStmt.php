<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `while (cond) { ... }`
 */
final class WhileStmt implements Node
{

    public function __construct(
        private readonly SigToken $keyword,
        private readonly Node $cond,
        private readonly SigToken $condClose,
        private readonly Block $block,
    )
    {
    }

    public function render(int $depth): string
    {
        return $this->keyword->text . ' ' . CondLayout::render($this->cond, $this->condClose, $depth)
            . ' ' . $this->block->render($depth);
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
