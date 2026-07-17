<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * One `catch (types $e)` clause of a try statement.
 *
 * Allowed forms for the type list (author's choice, read off the source):
 *   FLAT      `catch (A | B $e)` — types joined ` | ` WITH spaces (CatchSpacing
 *             standard), variable and `)` on the same line
 *   BROKEN    `(` on the catch line, each type on its own continuation line at
 *             depth+1 with a TRAILING ` |` operator, the variable after the last
 *             type, `)` on its own line at the catch's depth:
 *                 } catch (
 *                     AException |
 *                     BException $e
 *                 ) {
 *
 * A newline before the first type — or a comment riding on the `(` (a line-targeted
 * `@phpstan-ignore` on the catch) — selects the broken form. The trailing `|` here
 * is deliberate: it is the unanimous corpus form for catch unions (unlike boolean
 * chains, which lead with the operator).
 */
final class CatchClause
{

    /**
     * @param list<SigToken> $types
     * @param list<SigToken> $pipes one shorter than $types
     */
    public function __construct(
        private readonly SigToken $keyword,
        private readonly SigToken $open,
        private readonly array $types,
        private readonly array $pipes,
        private readonly ?SigToken $var,
        private readonly SigToken $close,
        private readonly Block $block,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->keyword);
        $e->space();
        $e->token($this->open);

        $broken = $this->open->trailingComment !== null
            || $this->types[0]->newlinesBefore() > 0
            || $this->close->newlinesBefore() > 0;

        if ($broken) {
            foreach ($this->types as $i => $type) {
                if ($i > 0) {
                    $e->space();
                    $e->token($this->pipes[$i - 1]); // trailing ` |` on the previous line
                }

                $e->newline($ctx->line + 1);
                $e->token($type);
            }
        } else {
            foreach ($this->types as $i => $type) {
                if ($i > 0) {
                    $e->space();
                    $e->token($this->pipes[$i - 1]);
                    $e->space();
                }

                $e->token($type);
            }
        }

        if ($this->var !== null) {
            $e->space();
            $e->token($this->var);
        }

        if ($broken) {
            $e->newline($ctx->line);
        }

        $e->token($this->close);
        $e->space();
        $this->block->render($e, $ctx);
    }

}
