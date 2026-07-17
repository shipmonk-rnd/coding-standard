<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function implode;

final class CatchClause
{

    /**
     * @param list<SigToken> $types
     */
    public function __construct(
        private readonly SigToken $keyword,
        private readonly array $types,
        private readonly ?SigToken $var,
        private readonly Block $block,
    )
    {
    }

    public function render(int $depth): string
    {
        $types = [];

        foreach ($this->types as $type) {
            $types[] = $type->text;
        }

        return $this->keyword->text . ' (' . implode(' | ', $types)
            . ($this->var !== null ? ' ' . $this->var->text : '')
            . ') ' . $this->block->render($depth);
    }

}
