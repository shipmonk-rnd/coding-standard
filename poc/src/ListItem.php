<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * An element of a comma-separated collection (array item, call argument, parameter,
 * closure use variable). Its source separator comma token — when present — is kept
 * so it can be re-emitted with its trailing trivia intact.
 */
interface ListItem extends Node
{

    public function commaToken(): ?SigToken;

}
