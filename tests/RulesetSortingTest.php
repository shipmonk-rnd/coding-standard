<?php declare(strict_types = 1);

namespace ShipmonkTests\CodingStyle;

use PHPUnit\Framework\TestCase;
use function implode;
use function natsort;
use function simplexml_load_file;

class RulesetSortingTest extends TestCase
{

    public function testRulesAreSortedAlphabetically(): void
    {
        $rulesetPath = __DIR__ . '/../ShipMonkCodingStandard/ruleset.xml';
        $this->assertFileExists($rulesetPath);

        $xml = simplexml_load_file($rulesetPath);
        self::assertNotFalse($xml, 'Failed to parse ruleset.xml');

        $rules = [];
        foreach ($xml->rule as $rule) {
            $ref = (string) $rule['ref'];
            if (!empty($ref)) {
                $rules[] = $ref;
            }
        }

        self::assertNotEmpty($rules, 'No rules found in ruleset.xml');

        $sortedRules = $rules;
        natsort($sortedRules);

        self::assertSame(
            $sortedRules,
            $rules,
            'Rules are not sorted alphabetically. Expected order: ' . implode(', ', $sortedRules),
        );
    }

}
