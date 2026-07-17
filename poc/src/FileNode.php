<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function array_slice;

/**
 * Template: `<?php` header, then each statement on its own line at depth 0
 * (StmtSeries), single trailing newline at EOF.
 *
 * Allowed exception: `declare(...)` may sit on the same line as `<?php` (the
 * shipmonk declare-on-first-line style) — both forms are in the allowed set.
 */
final class FileNode implements Node
{

    /**
     * @param list<Node> $stmts
     */
    public function __construct(
        private readonly SigToken $openTag,
        private readonly array $stmts,
        private readonly SigToken $eof,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->openTag);
        $stmts = $this->stmts;

        if ($stmts !== [] && $stmts[0] instanceof DeclareStmt && $stmts[0]->firstToken()->newlinesBefore() === 0) {
            $e->space();
            $stmts[0]->render($e, RenderCtx::atLine(0));
            $e->flushTrailing();
            $stmts = array_slice($stmts, 1);
        }

        StmtSeries::render($e, $stmts, 0, $this->eof);
        $e->newline(0);
    }

    public function firstToken(): SigToken
    {
        return $this->openTag;
    }

}
