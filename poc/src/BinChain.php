<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: operands joined by binary operators with exactly one space on each side.
 * PoC limitation: no break points at operator joints (multi-line operator chains are
 * repaired onto one line); a break-allowing template comes later (see notes/40 §10).
 */
final class BinChain implements Node
{

    /**
     * @param list<Node> $operands
     * @param list<SigToken> $ops one shorter than $operands
     */
    public function __construct(
        private readonly array $operands,
        private readonly array $ops,
    )
    {
    }

    public function render(int $depth): string
    {
        $out = $this->operands[0]->render($depth);

        foreach ($this->ops as $i => $op) {
            $out .= ' ' . $op->text . ' ' . $this->operands[$i + 1]->render($depth);
        }

        return $out;
    }

    public function firstToken(): SigToken
    {
        return $this->operands[0]->firstToken();
    }

}
