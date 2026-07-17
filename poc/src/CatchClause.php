<?php declare(strict_types = 1);

namespace ShipMonkFmt;

final class CatchClause
{

    /**
     * @param list<SigToken> $types
     */
    public function __construct(
        private readonly SigToken $keyword,
        private readonly array $types,
        private readonly ?SigToken $var,
        private readonly Block $block,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->keyword);
        $e->text(' (');

        foreach ($this->types as $i => $type) {
            if ($i > 0) {
                $e->text(' | ');
            }

            $e->token($type);
        }

        if ($this->var !== null) {
            $e->space();
            $e->token($this->var);
        }

        $e->text(') ');
        $this->block->render($e, $ctx);
    }

}
