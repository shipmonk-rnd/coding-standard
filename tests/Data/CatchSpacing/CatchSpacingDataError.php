<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Data\CatchSpacing;

use Exception;
use Throwable;

final class CatchSpacingDataError
{
    public function tryCatch(): void
    {
        try {
            $this->throwException();
        } catch (Exception| Throwable $e1) {

        }

        try {
            $this->throwException();
        } catch (Exception |Throwable $e2) {

        }

        try {
            $this->throwException();
        } catch (Exception | Throwable$e3) {

        }

        try {
            $this->throwException();
        } catch (
            Exception|
            Throwable $e4
        ) {
        }

    }

}
