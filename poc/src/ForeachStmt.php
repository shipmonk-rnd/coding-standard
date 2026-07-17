<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `foreach (subject as $value) { ... }` /
 * `foreach (subject as $key => $value) { ... }` — header on one line.
 */
final class ForeachStmt implements Node
{

    public function __construct(
        private readonly SigToken $keyword,
        private readonly Node $subject,
        private readonly ?Node $key,
        private readonly ?SigToken $byRef,
        private readonly Node $value,
        private readonly Block $block,
    )
    {
    }

    public function render(int $depth): string
    {
        return $this->keyword->text . ' (' . $this->subject->render($depth) . ' as '
            . ($this->key !== null ? $this->key->render($depth) . ' => ' : '')
            . ($this->byRef !== null ? '&' : '')
            . $this->value->render($depth)
            . ') ' . $this->block->render($depth);
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
