<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: ternary `cond ? then : else` (also short `cond ?: else`).
 *
 * Allowed forms (matching the existing MultilineTernary standard):
 *   FLAT      `$cond ? $a : $b`
 *   BROKEN    condition stays on its line, both branch operators LEADING at +1:
 *                 $cond
 *                     ? $a
 *                     : $b
 * Any break in the joints selects the fully broken form.
 */
final class Ternary implements Node
{

    public function __construct(
        private readonly Node $cond,
        private readonly SigToken $question,
        private readonly ?Node $then,
        private readonly SigToken $colon,
        private readonly Node $else,
    )
    {
    }

    public function render(int $depth): string
    {
        return $this->renderAt($depth + 1, $depth);
    }

    public function renderAt(int $lineIndent, int $firstDepth): string
    {
        $cond = $this->cond->render($firstDepth);

        if (!$this->hasJointBreaks()) {
            return $this->then === null
                ? $cond . ' ?: ' . $this->else->render($firstDepth)
                : $cond . ' ? ' . $this->then->render($firstDepth) . ' : ' . $this->else->render($firstDepth);
        }

        $ind = "\n" . Layout::indent($lineIndent);

        return $this->then === null
            ? $cond . $ind . '?: ' . $this->else->render($lineIndent)
            : $cond . $ind . '? ' . $this->then->render($lineIndent) . $ind . ': ' . $this->else->render($lineIndent);
    }

    public function hasJointBreaks(): bool
    {
        return $this->question->newlinesBefore() > 0
            || ($this->then !== null && $this->then->firstToken()->newlinesBefore() > 0)
            || $this->colon->newlinesBefore() > 0
            || $this->else->firstToken()->newlinesBefore() > 0;
    }

    public function firstToken(): SigToken
    {
        return $this->cond->firstToken();
    }

}
