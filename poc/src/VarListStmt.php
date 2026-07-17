<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function implode;

/**
 * Template: `static $x = 1, $y;` / `global $x;` — function-local static/global
 * variable declarations on one line.
 */
final class VarListStmt implements Node
{

    /**
     * @param non-empty-list<array{SigToken, ?Node}> $vars var, default
     */
    public function __construct(
        private readonly SigToken $keyword,
        private readonly array $vars,
    )
    {
    }

    public function render(int $depth): string
    {
        $parts = [];

        foreach ($this->vars as [$var, $default]) {
            $parts[] = $var->text . ($default !== null ? ' = ' . $default->render($depth) : '');
        }

        return $this->keyword->text . ' ' . implode(', ', $parts) . ';';
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
