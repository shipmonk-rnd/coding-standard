<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function count;

/**
 * One `conds => expr,` arm of a match.
 *
 * The condition list is a comma-separated collection (notes/03): conditions joined
 * `, ` when flat, or grouped into rows the author chose, with own-line comment rows
 * and blank-line grouping preserved. Unlike a bracketed collection it carries NO
 * mandatory trailing comma before `=>`. Trailing `// comments` on a condition ride
 * as its comma's trivia; the arm's own trailing comment rides on the arm comma.
 */
final class MatchArm implements Node
{

    /**
     * @param list<MatchCondItem|CommentRow> $conds empty when $default is set
     */
    public function __construct(
        private readonly ?SigToken $default,
        private readonly array $conds,
        private readonly SigToken $arrow,
        private readonly Node $body,
        private readonly ?SigToken $comma,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        if ($this->default !== null) {
            $e->token($this->default);
        } else {
            $this->renderConds($e, $ctx);
        }

        $e->space();
        $e->token($this->arrow);
        $e->space();
        $this->body->render($e, $ctx);
        $this->comma !== null ? $e->token($this->comma) : $e->layoutComma();
    }

    private function renderConds(Emitter $e, RenderCtx $ctx): void
    {
        $lastCond = $this->lastCondIndex();

        foreach ($this->conds as $i => $item) {
            $token = $item instanceof CommentRow ? $item->token : $item->firstToken();

            if ($i > 0) {
                $token->newlinesBefore() > 0
                    ? $e->newline($ctx->line, $token->newlinesBefore() >= 2)
                    : $e->space();
            }

            if ($item instanceof CommentRow) {
                CommentStmt::emitReindented($e, $item->token, $ctx->line);
                continue;
            }

            $item->render($e, $ctx);

            if ($i !== $lastCond) {
                // separator comma between conditions (re-emitted for its trivia)
                $comma = $item->commaToken();
                $comma !== null ? $e->token($comma) : $e->text(',');
            } elseif (($comma = $item->commaToken()) !== null && $comma->trailingComment !== null) {
                // a layout comma before `=>` is dropped — but its comment would vanish
                throw new FatalError('comment on a layout comma before "=>" has no allowed position', $comma->line);
            }
        }
    }

    private function lastCondIndex(): int
    {
        $last = 0;

        foreach ($this->conds as $i => $item) {
            if (!$item instanceof CommentRow) {
                $last = $i;
            }
        }

        return $last;
    }

    public function firstToken(): SigToken
    {
        return $this->default ?? $this->conds[0]->firstToken();
    }

}
