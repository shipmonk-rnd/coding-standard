<?php

class IgnoreDemo extends Base
{

    public function compat(): int // @phpstan-ignore return.unusedType (old dbal compat)
    {
        if (
            $this->ready()
            && !$this->dirty() // preloading dirty collection is too hard to handle
        ) {
            return 1;
        } elseif ($this->half()) { // @phpstan-ignore phpstanApi.instanceofType
            return 2;
        }

        $q = $this->builder()
            ->one() // this does not work
            ->two(...);

        $r = new Wrapper(new Inner( // @phpstan-ignore phpstanApi.constructor
            $q,
        ));

        return match ($r->kind()) {
            1, 2 => throw new LogicException('nope'),
            default => 0,
        };
    }

}
