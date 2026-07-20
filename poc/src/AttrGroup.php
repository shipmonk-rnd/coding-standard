<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * One attribute group `#[Name(args), Other]` — rendered flat, arguments per
 * CollectionLayout (may break if the author broke them).
 */
final class AttrGroup
{

    /**
     * @param list<array{SigToken, ?SigToken, list<ListItem|CommentRow>, ?SigToken}> $attrs
     *        name, argsOpen, args, argsClose
     */
    public function __construct(
        private readonly SigToken $open,
        private readonly array $attrs,
        private readonly SigToken $close,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->open);

        foreach ($this->attrs as $i => [$name, $argsOpen, $args, $argsClose]) {
            if ($i > 0) {
                $e->text(', ');
            }

            $e->token($name);

            if ($argsOpen !== null) {
                CollectionLayout::render($e, $argsOpen, $args, $argsClose, $ctx);
            }
        }

        $e->token($this->close);
    }

    public function firstToken(): SigToken
    {
        return $this->open;
    }

}
