<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * A node of the layout tree.
 *
 * render() IS the node's template: it derives the choice-point assignment from the
 * observed source gaps (via SigToken::newlinesBefore()) and emits the layout under
 * that assignment through the Emitter — the only way to produce output. Output
 * identical to the source slice = the source was in the allowed set (MATCH);
 * different = REPAIR to the closest allowed form; a placement the template has no
 * joint for = FatalError.
 */
interface Node
{

    public function render(Emitter $e, RenderCtx $ctx): void;

    public function firstToken(): SigToken;

}
