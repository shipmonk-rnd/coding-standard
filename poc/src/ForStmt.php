<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `for (init; cond; step) { ... }` — sections on one line
 * (PoC limitation, deliberate: no break points inside the header).
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

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->keyword);
        $e->text(' (');
        $this->list($e, $this->init, $ctx);
        $e->text('; ');
        $this->cond?->render($e, $ctx);
        $e->text('; ');
        $this->list($e, $this->step, $ctx);
        $e->text(') ');
        $this->block->render($e, $ctx);
    }

    /**
     * @param list<Node> $exprs
     */
    private function list(Emitter $e, array $exprs, RenderCtx $ctx): void
    {
        foreach ($exprs as $i => $expr) {
            if ($i > 0) {
                $e->text(', ');
            }

            $expr->render($e, $ctx);
        }
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
