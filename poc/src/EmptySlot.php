<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * A skipped element in array destructuring: `[, $pretty] = ...`,
 * `foreach ($m as [, $type, $name])`. Renders nothing; its comma is the anchor.
 */
final class EmptySlot implements ListItem
{

    public function __construct(
        private readonly SigToken $comma,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
    }

    public function firstToken(): SigToken
    {
        return $this->comma;
    }

    public function commaToken(): SigToken
    {
        return $this->comma;
    }

}
