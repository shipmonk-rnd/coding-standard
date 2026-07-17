<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Type declaration (`?Foo`, `A|B`, `A&B`, `(A&B)|null`) — tokens joined with NO
 * whitespace (matches the existing DNFTypeHintFormat: no spaces around operators).
 */
final class TypeNode implements Node
{

    /**
     * @param non-empty-list<SigToken> $tokens
     */
    public function __construct(
        private readonly array $tokens,
    )
    {
    }

    public function render(int $depth): string
    {
        $out = '';

        foreach ($this->tokens as $token) {
            $out .= $token->text;
        }

        return $out;
    }

    public function firstToken(): SigToken
    {
        return $this->tokens[0];
    }

}
