<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Data\CatchSpacing;

use Exception;
use Throwable;

final class CatchSpacingDataSpace
{
    public function tryCatch(): void
    {
        try {
            $this->throwException();
        } catch ( Exception | Throwable $e) {

        }
    }
}
