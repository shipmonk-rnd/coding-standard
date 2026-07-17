<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * A node of the layout tree.
 *
 * render() IS the node's template (PoC simplification, see notes/40 §9): it derives the
 * choice-point assignment from the observed source gaps (via SigToken::newlinesBefore())
 * and prints the layout under that assignment. Output identical to the source slice =
 * the source was in the allowed set (MATCH); different = REPAIR to the closest allowed
 * form; a placement the template has no joint for = FatalError.
 */
interface Node
{

    public function render(int $depth): string;

    public function firstToken(): SigToken;

}
