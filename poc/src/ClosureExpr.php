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

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        if ($this->static !== null) {
            $e->token($this->static);
            $e->space();
        }

        $e->token($this->keyword);
        $e->space();
        CollectionLayout::render($e, $this->paramsOpen, $this->params, $this->paramsClose, $ctx, onePerRow: true);

        if ($this->usesOpen !== null) {
            $e->text(' use ');
            CollectionLayout::render($e, $this->usesOpen, $this->uses, $this->usesClose, $ctx, onePerRow: true);
        }

        if ($this->returnType !== null) {
            $e->text(': ');
            $this->returnType->render($e, $ctx);
        }

        $e->space();
        $this->body->render($e, RenderCtx::atLine($ctx->line));
    }

    public function firstToken(): SigToken
    {
        return $this->static ?? $this->keyword;
    }

}
