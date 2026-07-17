<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Function call `name(...)` — no space between the name and `(`; argument list layout
 * delegated to the shared collection template (trailing comma iff broken — matches
 * the existing standard's RequireTrailingCommaInCall + DisallowTrailingCommaInCall
 * onlySingleLine, see notes/01).
 */
final class CallExpr implements Node
{

    /**
     * @param list<ArrayItem|CommentRow> $items
     */
    public function __construct(
        private readonly SigToken $name,
        private readonly SigToken $open,
        private readonly array $items,
        private readonly SigToken $close,
    )
    {
    }

    public function render(int $depth): string
    {
        return $this->name->text . CollectionLayout::render($this->open, $this->items, $this->close, $depth);
    }

    public function firstToken(): SigToken
    {
        return $this->name;
    }

}
