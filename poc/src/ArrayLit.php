<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Array literal `[...]` — layout delegated to the shared collection template.
 */
final class ArrayLit implements Node
{

    /**
     * @param list<ListItem|CommentRow> $items
     */
    public function __construct(
        private readonly SigToken $open,
        private readonly array $items,
        private readonly SigToken $close,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        CollectionLayout::render($e, $this->open, $this->items, $this->close, $ctx);
    }

    public function firstToken(): SigToken
    {
        return $this->open;
    }

}
