<?php declare(strict_types = 1);

namespace ShipMonkFmt;

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
        private readonly SigToken $semi,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->keyword);

        foreach ($this->vars as $i => [$var, $default]) {
            $i > 0 ? $e->text(', ') : $e->space();
            $e->token($var);

            if ($default !== null) {
                $e->text(' = ');
                $default->render($e, $ctx);
            }
        }

        $e->token($this->semi);
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
