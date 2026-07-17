<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `try { ... } catch (A | B $e) { ... } finally { ... }` — `} catch (` on
 * one line, catch types joined ` | ` WITH spaces (existing CatchSpacing standard).
 */
final class TryStmt implements Node
{

    /**
     * @param list<CatchClause> $catches
     */
    public function __construct(
        private readonly SigToken $keyword,
        private readonly Block $block,
        private readonly array $catches,
        private readonly ?Block $finally,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        $e->token($this->keyword);
        $e->space();
        $this->block->render($e, $ctx);

        foreach ($this->catches as $catch) {
            $e->space();
            $catch->render($e, $ctx);
        }

        if ($this->finally !== null) {
            $e->text(' finally ');
            $this->finally->render($e, $ctx);
        }
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
