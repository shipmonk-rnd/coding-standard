<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `for (init; cond; step) { ... }` — sections on one line
 * (PoC limitation: no break points inside the header).
 */
final class ForStmt implements Node
{

    /**
     * @param list<Node> $init
     * @param list<Node> $step
     */
    public function __construct(
        private readonly SigToken $keyword,
        private readonly array $init,
        private readonly ?Node $cond,
        private readonly array $step,
        private readonly Block $block,
    )
    {
    }

    public function render(int $depth): string
    {
        return $this->keyword->text . ' ('
            . $this->renderList($this->init, $depth) . '; '
            . ($this->cond?->render($depth) ?? '') . '; '
            . $this->renderList($this->step, $depth)
            . ') ' . $this->block->render($depth);
    }

    /**
     * @param list<Node> $exprs
     */
    private function renderList(array $exprs, int $depth): string
    {
        $parts = [];

        foreach ($exprs as $expr) {
            $parts[] = $expr->render($depth);
        }

        return implode(', ', $parts);
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
