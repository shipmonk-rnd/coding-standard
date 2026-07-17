<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `declare(strict_types = 1);` — fully mandated horizontal layout
 * (spaces around `=`, no space inside the parentheses), no choice points.
 */
final class DeclareStmt implements Node
{

    public function __construct(
        private readonly SigToken $keyword,
        private readonly SigToken $directive,
        private readonly Node $value,
        private readonly SigToken $semi,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->keyword);
        $e->text('(');
        $e->token($this->directive);
        $e->text(' = ');
        $this->value->render($e, $ctx);
        $e->text(')');
        $e->token($this->semi);
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
