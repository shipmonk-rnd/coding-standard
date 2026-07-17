<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Array literal `[...]` — layout delegated to the shared collection template.
 */
final class ArrayLit implements Node
{

    /**
     * @param list<ArrayItem|CommentRow> $items
     */
    public function __construct(
        private readonly SigToken $open,
        private readonly array $items,
        private readonly SigToken $close,
    )
    {
    }

    public function render(int $depth): string
    {
        return CollectionLayout::render($this->open, $this->items, $this->close, $depth);
    }

    public function firstToken(): SigToken
    {
        return $this->open;
    }

}
