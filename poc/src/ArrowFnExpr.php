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
        private readonly Node $body,
    )
    {
    }

    public function render(int $depth): string
    {
        return ($this->static !== null ? $this->static->text . ' ' : '')
            . $this->keyword->text . ' '
            . CollectionLayout::render($this->paramsOpen, $this->params, $this->paramsClose, $depth, onePerRow: true)
            . ($this->returnType !== null ? ': ' . $this->returnType->render($depth) : '')
            . ' => ' . $this->body->render($depth);
    }

    public function firstToken(): SigToken
    {
        return $this->static ?? $this->keyword;
    }

}
