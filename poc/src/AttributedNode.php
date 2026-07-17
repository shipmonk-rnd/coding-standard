<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * A declaration prefixed by attribute groups — each group on its own line directly
 * above the target, no blank line between (AttributeAndTargetSpacing +
 * DisallowMultipleAttributesPerLine, notes/01).
 */
final class AttributedNode implements Node
{

    /**
     * @param non-empty-list<AttrGroup> $groups
     */
    public function __construct(
        private readonly array $groups,
        private readonly Node $target,
    )
    {
    }

    public function render(int $depth): string
    {
        $out = '';

        foreach ($this->groups as $group) {
            $out .= $group->render($depth) . "\n" . Layout::indent($depth);
        }

        return $out . $this->target->render($depth);
    }

    public function firstToken(): SigToken
    {
        return $this->groups[0]->firstToken();
    }

}
