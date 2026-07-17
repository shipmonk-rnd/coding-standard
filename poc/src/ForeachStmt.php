<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `foreach (subject as $value) { ... }` /
 * `foreach (subject as $key => $value) { ... }` — header on one line.
 */
final class ForeachStmt implements Node
{

    public function __construct(
        private readonly SigToken $keyword,
        private readonly Node $subject,
        private readonly ?Node $key,
        private readonly ?SigToken $byRef,
        private readonly Node $value,
        private readonly Block $block,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->keyword);
        $e->text(' (');
        $this->subject->render($e, $ctx);
        $e->text(' as ');

        if ($this->key !== null) {
            $this->key->render($e, $ctx);
            $e->text(' => ');
        }

        if ($this->byRef !== null) {
            $e->token($this->byRef);
        }

        $this->value->render($e, $ctx);
        $e->text(') ');
        $this->block->render($e, $ctx);
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
