<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: operands joined by binary operators.
 *
 * Allowed forms per operator joint (author's choice, read off the source):
 *   FLAT      ` op ` — exactly one space on each side
 *   BROKEN    newline, LEADING operator on the continuation line at $ctx->cont:
 *                 $a
 *                 && $b
 * A trailing-operator break (`$a &&<newline>$b`) is repaired to the leading form.
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

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $this->operands[0]->render($e, $ctx);
        $current = $ctx;

        foreach ($this->ops as $i => $op) {
            $operand = $this->operands[$i + 1];

            if ($op->newlinesBefore() > 0 || $operand->firstToken()->newlinesBefore() > 0) {
                $e->newline($ctx->cont);
                $current = RenderCtx::atLine($ctx->cont);
            } else {
                $e->space();
            }

            $e->token($op);
            $e->space();
            $operand->render($e, $current);
        }
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
