<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard;

use PHPUnit\Framework\TestCase;
use function basename;
use function copy;
use function escapeshellarg;
use function exec;
use function file_get_contents;
use function glob;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * phpcs:disable SlevomatCodingStandard.Commenting.ForbiddenAnnotations.AnnotationForbidden
 */
class AutofixTest extends TestCase
{

    /**
     * @dataProvider provideTestFiles
     */
    public function testAutofix(
        string $testFile,
        string $fixedFile,
    ): void
    {
        self::assertFileExists($testFile, 'Test file does not exist');
        self::assertFileExists($fixedFile, 'Fixed file does not exist');

        // Create a temporary copy of the test file
        $tempFile = tempnam(sys_get_temp_dir(), 'phpcs_test_');
        self::assertNotFalse($tempFile, 'Failed to create temporary file');

        copy($testFile, $tempFile);

        // Run phpcbf on the temporary file
        $rulesetPath = __DIR__ . '/../ShipMonkCodingStandard/ruleset.xml';
        $command = sprintf(
            '%s --standard=%s %s 2>&1',
            escapeshellarg(__DIR__ . '/../vendor/bin/phpcbf'),
            escapeshellarg($rulesetPath),
            escapeshellarg($tempFile),
        );

        exec($command, $output, $exitCode);

        // Read the fixed content
        $actualContent = file_get_contents($tempFile);
        $expectedContent = file_get_contents($fixedFile);

        // Clean up
        unlink($tempFile);

        self::assertNotFalse($actualContent, 'Failed to read temporary file');
        self::assertNotFalse($expectedContent, 'Failed to read fixed file');

        self::assertSame(
            $expectedContent,
            $actualContent,
            'The autofixed content does not match the expected .fixed file',
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideTestFiles(): iterable
    {
        $dataDir = __DIR__ . '/Data';
        $testFiles = glob($dataDir . '/*.php');

        self::assertNotFalse($testFiles, 'Failed to read test files');
        self::assertNotEmpty($testFiles, 'No test files found in tests/Data directory');

        foreach ($testFiles as $testFile) {
            $fixedFile = $testFile . '.fixed';
            $fileName = basename($testFile);
            yield $fileName => [$testFile, $fixedFile];
        }
    }

}
