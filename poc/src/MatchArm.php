<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * One `conds => expr,` arm of a match (conditions joined `, `, trailing comma
 * mandatory, optional trailing comment).
 */
final class MatchArm implements Node
{

    /**
     * @param list<Node> $conds empty when $default is set
     */
    public function __construct(
        private readonly ?SigToken $default,
        private readonly array $conds,
        private readonly Node $body,
        private readonly ?SigToken $trailingComment,
    )
    {
    }

    public function render(int $depth): string
    {
        if ($this->default !== null) {
            $label = $this->default->text;
        } else {
            $parts = [];

            foreach ($this->conds as $cond) {
                $parts[] = $cond->render($depth);
            }

            $label = implode(', ', $parts);
        }

        return $label . ' => ' . $this->body->render($depth) . ','
            . ($this->trailingComment !== null ? ' ' . $this->trailingComment->text : '');
    }

    public function firstToken(): SigToken
    {
        return $this->default ?? $this->conds[0]->firstToken();
    }

}
