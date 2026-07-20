<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `[static ]fn (params)[: type] => expr` — space after `fn`.
 */
final class ArrowFnExpr implements Node
{

    /**
     * @param list<ListItem|CommentRow> $params
     */
    public function __construct(
        private readonly ?SigToken $static,
        private readonly SigToken $keyword,
        private readonly SigToken $paramsOpen,
        private readonly array $params,
        private readonly SigToken $paramsClose,
        private readonly ?TypeNode $returnType,
        private readonly SigToken $arrow,
        private readonly Node $body,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        if ($this->static !== null) {
            $e->token($this->static);
            $e->space();
        }

        $e->token($this->keyword);
        $e->space();
        CollectionLayout::render($e, $this->paramsOpen, $this->params, $this->paramsClose, $ctx, onePerRow: true);

        if ($this->returnType !== null) {
            $e->text(': ');
            $this->returnType->render($e, $ctx);
        }

        $e->space();
        $e->token($this->arrow);
        $e->space();
        $this->body->render($e, $ctx);
    }

    public function firstToken(): SigToken
    {
        return $this->static ?? $this->keyword;
    }

}
