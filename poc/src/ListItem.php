<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * An element of a comma-separated collection (array item, call argument, parameter,
 * closure use variable) that may carry a trailing same-line comment (`1, // one`).
 */
interface ListItem extends Node
{

    public function trailingComment(): ?SigToken;

}
