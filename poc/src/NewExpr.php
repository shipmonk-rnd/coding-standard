<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `new ClassName(args)` — argument layout per CollectionLayout;
 * parens-less `new X` preserved as written (adding tokens is a declared-edit
 * feature for later, notes/50 §8).
 */
final class NewExpr implements Node
{

    /**
     * @param list<ListItem|CommentRow> $args
     */
    public function __construct(
        private readonly SigToken $keyword,
        private readonly Node $class,
        private readonly ?SigToken $argsOpen,
        private readonly array $args,
        private readonly ?SigToken $argsClose,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->keyword);
        $e->space();
        $this->class->render($e, $ctx);

        if ($this->argsOpen !== null) {
            CollectionLayout::render($e, $this->argsOpen, $this->args, $this->argsClose, $ctx);
        }
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
