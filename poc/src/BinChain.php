<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: operands joined by binary operators.
 *
 * Allowed forms per operator joint (author's choice, read off the source):
 *   FLAT      ` op ` — exactly one space on each side
 *   BROKEN    newline, LEADING operator on the continuation line:
 *                 $a
 *                 && $b
 * A trailing-operator break (`$a &&<newline>$b`) is repaired to the leading form
 * (matches the existing standard's DisallowTrailingMultiLineTernaryOperator spirit).
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
        return $this->renderAt($depth + 1, $depth);
    }

    /**
     * @param int $lineIndent indent of broken continuation lines
     * @param int $firstDepth depth for the first operand (its line's depth)
     */
    public function renderAt(int $lineIndent, int $firstDepth): string
    {
        $out = $this->operands[0]->render($firstDepth);
        $current = $firstDepth;

        foreach ($this->ops as $i => $op) {
            $operand = $this->operands[$i + 1];

            if ($op->newlinesBefore() > 0 || $operand->firstToken()->newlinesBefore() > 0) {
                $current = $lineIndent;
                $out .= "\n" . Layout::indent($lineIndent) . $op->text . ' ' . $operand->render($current);
            } else {
                $out .= ' ' . $op->text . ' ' . $operand->render($current);
            }
        }

        return $out;
    }

    public function hasJointBreaks(): bool
    {
        foreach ($this->ops as $i => $op) {
            if ($op->newlinesBefore() > 0 || $this->operands[$i + 1]->firstToken()->newlinesBefore() > 0) {
                return true;
            }
        }

        return false;
    }

    public function firstToken(): SigToken
    {
        return $this->operands[0]->firstToken();
    }

}
