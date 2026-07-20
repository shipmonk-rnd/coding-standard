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

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        foreach ($this->groups as $group) {
            $group->render($e, $ctx);
            $e->newline($ctx->line);
        }

        $this->target->render($e, $ctx);
    }

    public function firstToken(): SigToken
    {
        return $this->groups[0]->firstToken();
    }

    /** The decorated declaration (used to classify a member for spacing). */
    public function target(): Node
    {
        return $this->target;
    }

}
