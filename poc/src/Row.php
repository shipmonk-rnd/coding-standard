<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * One row of a broken collection — the per-collection "choice assignment" as data
 * (notes/50 §7): which elements the author grouped on this line, and whether a
 * blank line precedes it.
 */
final class Row
{

    /**
     * @param non-empty-list<ListItem> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly bool $blankBefore,
    )
    {
    }

}
