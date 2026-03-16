<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard;

use PHPUnit\Framework\TestCase;
use function escapeshellarg;
use function exec;
use function implode;
use function strpos;
use const PHP_EOL;

class DocCommentSpacingTest extends TestCase
{

    /**
     * Verify that @template* annotations are not reordered relative to each other.
     * The order of @template, @template-covariant, and @template-contravariant determines
     * template parameter position in PHPStan's type system, so reordering changes semantics.
     */
    public function testTemplateAnnotationsAreNotReordered(): void
    {
        $output = self::runPhpcs(__DIR__ . '/Data/DocCommentSpacing/TemplateAnnotationOrderCorrect.php');
        self::assertNoSniffErrors('SlevomatCodingStandard.Commenting.DocCommentSpacing', $output);
    }

    /**
     * @return list<string>
     */
    private static function runPhpcs(string $filePath): array
    {
        $phpcs = __DIR__ . '/../vendor/bin/phpcs';
        $output = [];
        exec($phpcs . ' --standard=ShipMonkCodingStandard -s ' . escapeshellarg($filePath), $output);
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

}
