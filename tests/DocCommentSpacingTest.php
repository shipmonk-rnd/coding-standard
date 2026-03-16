<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard;

use PHPUnit\Framework\TestCase;
use function escapeshellarg;
use function exec;
use function implode;
use function preg_match;
use function strpos;
use const PHP_EOL;

class DocCommentSpacingTest extends TestCase
{

    private const SNIFF_PREFIX = 'SlevomatCodingStandard.Commenting.DocCommentSpacing';

    /**
     * Verify that @template* annotations are not reordered relative to each other.
     * The order of @template, @template-covariant, and @template-contravariant determines
     * template parameter position in PHPStan's type system, so reordering changes semantics.
     */
    public function testTemplateAnnotationsAreNotReordered(): void
    {
        $output = self::runPhpcs(__DIR__ . '/Data/DocCommentSpacing/TemplateAnnotationOrderCorrect.php');
        self::assertNoSniffErrors(self::SNIFF_PREFIX, $output);
    }

    public function testWrongAnnotationOrderIsReported(): void
    {
        $output = self::runPhpcs(__DIR__ . '/Data/DocCommentSpacing/TemplateAnnotationOrderWrong.php');

        // @extends before @template
        self::assertSniffErrorOnLine(self::SNIFF_PREFIX . '.IncorrectOrderOfAnnotationsInGroup', $output, 7);

        // @implements before @template
        self::assertSniffErrorOnLine(self::SNIFF_PREFIX . '.IncorrectOrderOfAnnotationsInGroup', $output, 17);

        // @implements before @extends
        self::assertSniffErrorOnLine(self::SNIFF_PREFIX . '.IncorrectOrderOfAnnotationsInGroup', $output, 28);

        // blank line between annotations in same group
        self::assertSniffErrorOnLine(self::SNIFF_PREFIX . '.IncorrectAnnotationsGroup', $output, 38);
    }

    /**
     * @return list<string>
     */
    private static function runPhpcs(string $filePath): array
    {
        $phpcs = __DIR__ . '/../vendor/bin/phpcs';
        $output = [];
        exec($phpcs . ' --standard=ShipMonkCodingStandard --no-colors -s ' . escapeshellarg($filePath), $output);
        return $output;
    }

    /**
     * @param list<string> $phpcsOutput
     */
    private static function assertNoSniffErrors(
        string $sniffPrefix,
        array $phpcsOutput,
    ): void
    {
        $relevantErrors = [];

        foreach ($phpcsOutput as $line) {
            if (strpos($line, $sniffPrefix) !== false) {
                $relevantErrors[] = $line;
            }
        }

        self::assertSame(
            [],
            $relevantErrors,
            'Expected no ' . $sniffPrefix . ' errors, but found:' . PHP_EOL . implode(PHP_EOL, $relevantErrors),
        );
    }

    /**
     * @param list<string> $phpcsOutput
     */
    private static function assertSniffErrorOnLine(
        string $sniffCode,
        array $phpcsOutput,
        int $expectedLine,
    ): void
    {
        $currentLine = 0;
        $found = false;

        foreach ($phpcsOutput as $outputLine) {
            if (preg_match('~^\s*(\d+)\s*\|~', $outputLine, $matches) === 1) {
                $currentLine = (int) $matches[1];
            }

            if (strpos($outputLine, $sniffCode) !== false && $currentLine === $expectedLine) {
                $found = true;
                break;
            }
        }

        self::assertTrue(
            $found,
            'Expected ' . $sniffCode . ' error on line ' . $expectedLine
            . ', but not found in output:' . PHP_EOL . implode(PHP_EOL, $phpcsOutput),
        );
    }

}
