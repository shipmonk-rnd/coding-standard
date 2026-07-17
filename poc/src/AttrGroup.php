<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function implode;

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
    )
    {
    }

    public function render(int $depth): string
    {
        $parts = [];

        foreach ($this->attrs as [$name, $argsOpen, $args, $argsClose]) {
            $part = $name->text;

            if ($argsOpen !== null) {
                $part .= CollectionLayout::render($argsOpen, $args, $argsClose, $depth);
            }

            $parts[] = $part;
        }

        return '#[' . implode(', ', $parts) . ']';
    }

    public function firstToken(): SigToken
    {
        return $this->open;
    }

}
