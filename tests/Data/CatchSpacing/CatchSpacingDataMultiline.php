<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Data\CatchSpacing;

use Exception;
use Throwable;

final class CatchSpacingDataMultiline
{

    public function tryFail(): void
    {
        try {
            $this->throwException();
        } catch (
        Exception |
        Throwable $e
        ) {

        }
    }
    public function tryFail2(): void
    {
        try {
            $this->throwException();
        } catch (
            Exception |
            Throwable $e
        ) {

        }

        try {
            $this->throwException();
        } catch (
        Exception
        | Throwable $e
        ) {

        }
    }

    public function tryPass(): void
    {
        try {
            $this->throwException();
        } catch (
            Exception |
            Throwable $e
        ) {

        }
    }
}
