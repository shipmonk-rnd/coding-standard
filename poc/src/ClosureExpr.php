<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `[static ]function (params)[ use (vars)][: type] { ... }` — space after
 * the `function` keyword, body brace on the SAME line (closure convention; contrast
 * FunctionDecl's Allman brace).
 */
final class ClosureExpr implements Node
{

    /**
     * @param list<ListItem|CommentRow> $params
     * @param list<ListItem|CommentRow> $uses
     */
    public function __construct(
        private readonly ?SigToken $static,
        private readonly SigToken $keyword,
        private readonly SigToken $paramsOpen,
        private readonly array $params,
        private readonly SigToken $paramsClose,
        private readonly ?SigToken $usesOpen,
        private readonly array $uses,
        private readonly ?SigToken $usesClose,
        private readonly ?TypeNode $returnType,
        private readonly Block $body,
    )
    {
    }

    public function render(int $depth): string
    {
        $out = ($this->static !== null ? $this->static->text . ' ' : '')
            . $this->keyword->text . ' '
            . CollectionLayout::render($this->paramsOpen, $this->params, $this->paramsClose, $depth, onePerRow: true);

        if ($this->usesOpen !== null) {
            $out .= ' use ' . CollectionLayout::render($this->usesOpen, $this->uses, $this->usesClose, $depth, onePerRow: true);
        }

        if ($this->returnType !== null) {
            $out .= ': ' . $this->returnType->render($depth);
        }

        return $out . ' ' . $this->body->render($depth);
    }

    public function firstToken(): SigToken
    {
        return $this->static ?? $this->keyword;
    }

}
