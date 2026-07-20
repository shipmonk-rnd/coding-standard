<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `case Name;` / `case Name = value;`
 */
final class EnumCase implements Node
{

    public function __construct(
        private readonly SigToken $keyword,
        private readonly SigToken $name,
        private readonly ?Node $value,
        private readonly SigToken $semi,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->keyword);
        $e->space();
        $e->token($this->name);

        if ($this->value !== null) {
            $e->text(' = ');
            $this->value->render($e, $ctx);
        }

        $e->token($this->semi);
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
