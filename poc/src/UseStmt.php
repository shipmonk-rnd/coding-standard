<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `use X;` / `use function x;` / `use const X;` / `use X as Y;`
 * (also trait use inside a class body). One import per statement.
 */
final class UseStmt implements Node
{

    public function __construct(
        private readonly SigToken $keyword,
        private readonly ?SigToken $kind,
        private readonly SigToken $name,
        private readonly ?SigToken $alias,
        private readonly SigToken $semi,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->keyword);

        if ($this->kind !== null) {
            $e->space();
            $e->token($this->kind);
        }

        $e->space();
        $e->token($this->name);

        if ($this->alias !== null) {
            $e->text(' as ');
            $e->token($this->alias);
        }

        $e->token($this->semi);
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
