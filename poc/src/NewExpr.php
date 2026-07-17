<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `new ClassName(args)` — argument layout per CollectionLayout;
 * parens-less `new X` preserved as written (adding tokens is out of formatter scope).
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

    public function render(int $depth): string
    {
        $out = $this->keyword->text . ' ' . $this->class->render($depth);

        if ($this->argsOpen !== null) {
            $out .= CollectionLayout::render($this->argsOpen, $this->args, $this->argsClose, $depth);
        }

        return $out;
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
