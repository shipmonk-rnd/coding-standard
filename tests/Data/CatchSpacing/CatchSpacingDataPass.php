<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Data\CatchSpacing;

use Exception;
use LogicException;
use Throwable;

final class CatchSpacingDataPass
{
    public function tryCatch(): void
    {
        try {
            $this->throwException();
        } catch (Exception | Throwable | LogicException $e) {

        }
    }

    private function throwException(): void
    {
        throw new LogicException('foo');
    }

}
