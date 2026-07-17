<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `do { ... } while (cond);`
 */
final class DoWhileStmt implements Node
{

    public function __construct(
        private readonly SigToken $keyword,
        private readonly Block $block,
        private readonly Cond $cond,
    )
    {
    }

    public function render(int $depth): string
    {
        return $this->keyword->text . ' ' . $this->block->render($depth)
            . ' while ' . $this->cond->render($depth) . ';';
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
