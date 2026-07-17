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
 *
 * NOTE (notes/60): corpus code also contains continuations ALIGNED with the first
 * operand; allowing that was tried and reverted — indentation is horizontal and
 * therefore mandated, never an observable choice point (the perturbation fuzz
 * caught the violation). Aligned chains are repaired to $ctx->cont.
 */
final class BinChain implements Node
{

    /**
     * @param list<Node> $operands
     * @param list<SigToken> $ops one shorter than $operands
     * @param array<int, list<SigToken>> $opComments own-line comment rows preceding
     *        the operator at that index — they force the joint broken and are
     *        re-indented onto their own continuation lines
     */
    public function __construct(
        private readonly array $operands,
        private readonly array $ops,
        private readonly array $opComments = [],
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $this->operands[0]->render($e, $ctx);
        $current = $ctx;

        foreach ($this->ops as $i => $op) {
            $operand = $this->operands[$i + 1];
            $comments = $this->opComments[$i] ?? [];

            foreach ($comments as $comment) {
                $e->lineBreak($comment, $ctx->cont);
                CommentStmt::emitReindented($e, $comment, $ctx->cont);
            }

            if ($comments !== [] || $op->newlinesBefore() > 0 || $operand->firstToken()->newlinesBefore() > 0) {
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
        if ($this->opComments !== []) {
            return true;
        }

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
