<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function count;

/**
 * Named function / method declaration.
 *
 * Template: `[modifiers] function name(params)[: type]` with the body brace on its
 * OWN line (Allman, per OpeningFunctionBraceBsdAllman — notes/01), or `;` for
 * abstract/interface methods.
 *
 * Structural trigger (count-based, NOT width-based — notes/02): 2+ parameters force
 * the parameter list broken, one parameter per line. Signature-line trailing
 * comments ride as trivia and are flushed by the brace's newline.
 */
final class FunctionDecl implements Node
{

    /**
     * @param list<SigToken> $modifiers
     * @param list<ListItem|CommentRow> $params
     */
    public function __construct(
        private readonly array $modifiers,
        private readonly SigToken $keyword,
        private readonly SigToken $name,
        private readonly SigToken $paramsOpen,
        private readonly array $params,
        private readonly SigToken $paramsClose,
        private readonly ?TypeNode $returnType,
        private readonly ?Block $body,
        private readonly ?SigToken $semi,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        foreach ($this->modifiers as $modifier) {
            $e->token($modifier);
            $e->space();
        }

        $e->token($this->keyword);
        $e->space();
        $e->token($this->name);
        CollectionLayout::render(
            $e,
            $this->paramsOpen,
            $this->params,
            $this->paramsClose,
            $ctx,
            forceBroken: count($this->params) >= 2,
            onePerRow: true,
        );

        if ($this->returnType !== null) {
            $e->text(': ');
            $this->returnType->render($e, $ctx);
        }

        if ($this->body === null) {
            $e->token($this->semi);

            return;
        }

        $e->newline($ctx->line);
        $this->body->render($e, $ctx);
    }

    public function firstToken(): SigToken
    {
        return $this->modifiers[0] ?? $this->keyword;
    }

}
