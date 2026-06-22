<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Data\DoubleArrowSpacing;

class DoubleArrowSpacingErrors
{

    /**
     * @return array<int, string>
     */
    public function run(): array
    {
        $noSpaceBefore = [1=> 'a'];
        $noSpaceAfter = [2 =>'b'];
        $noSpaceBoth = [3=>'c'];

        return $noSpaceBefore + $noSpaceAfter + $noSpaceBoth;
    }

}
