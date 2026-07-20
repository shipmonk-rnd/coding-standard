<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function count;

/**
 * Template:
 *     switch (subject) {
 *         case expr:
 *             stmts...
 *         default:
 *             stmts...
 *     }
 * Blank lines between cases and between statements preserved 0-1.
 */
final class SwitchStmt implements Node
{

    /**
     * @param list<SwitchCase> $cases
     */
    public function __construct(
        private readonly SigToken $keyword,
        private readonly Cond $subject,
        private readonly SigToken $braceOpen,
        private readonly array $cases,
        private readonly SigToken $braceClose,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->keyword);
        $e->space();
        $this->subject->render($e, $ctx->line);
        $e->space();
        $e->token($this->braceOpen);

        foreach ($this->cases as $i => $case) {
            $e->lineBreak($case->firstToken(), $ctx->line + 1);
            $boundary = $i + 1 < count($this->cases) ? $this->cases[$i + 1]->firstToken() : $this->braceClose;
            $case->renderCase($e, $ctx->line + 1, $boundary);
        }

        $e->newline($ctx->line);
        $e->token($this->braceClose);
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
