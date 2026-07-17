<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * One `case expr:` / `default:` arm of a switch, statements at +1.
 */
final class SwitchCase
{

    /**
     * @param list<Node> $stmts
     */
    public function __construct(
        private readonly SigToken $keyword,
        private readonly ?Node $expr,
        private readonly SigToken $colon,
        private readonly array $stmts,
    )
    {
    }

    public function renderCase(Emitter $e, int $depth, SigToken $boundary): void
    {
        $e->token($this->keyword);

        if ($this->expr !== null) {
            $e->space();
            $this->expr->render($e, RenderCtx::atLine($depth));
        }

        $e->token($this->colon);
        StmtSeries::render($e, $this->stmts, $depth + 1, $boundary);
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
