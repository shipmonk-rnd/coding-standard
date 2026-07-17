<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `namespace Foo\Bar;`
 */
final class NamespaceStmt implements Node
{

    public function __construct(
        private readonly SigToken $keyword,
        private readonly SigToken $name,
        private readonly SigToken $semi,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->keyword);
        $e->space();
        $e->token($this->name);
        $e->token($this->semi);
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
