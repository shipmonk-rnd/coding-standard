<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * One `conds => expr,` arm of a match (conditions joined `, `, trailing comma
 * mandatory; trailing comments ride as comma trivia).
 */
final class MatchArm implements Node
{

    /**
     * @param list<Node> $conds empty when $default is set
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
            // condition ROWS are the author's grouping, preserved like collections
            foreach ($this->conds as $i => $cond) {
                if ($i > 0) {
                    $e->text(',');

                    if ($cond->firstToken()->newlinesBefore() > 0) {
                        $e->newline($ctx->line);
                    } else {
                        $e->space();
                    }
                }

                $cond->render($e, $ctx);
            }
        }

        $e->space();
        $e->token($this->arrow);
        $e->space();
        $this->body->render($e, $ctx);
        $this->comma !== null ? $e->token($this->comma) : $e->text(',');
    }

    public function firstToken(): SigToken
    {
        return $this->default ?? $this->conds[0]->firstToken();
    }

}
