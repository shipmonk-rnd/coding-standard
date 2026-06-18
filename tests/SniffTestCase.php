<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\DummyFile;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Runner;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHPUnit\Framework\TestCase;
use function define;
use function defined;
use function file_get_contents;
use function file_put_contents;
use function getenv;
use function is_file;
use function str_replace;

abstract class SniffTestCase extends TestCase
{

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__ . '/../vendor/squizlabs/php_codesniffer/autoload.php';
    }

    protected static function checkFile(string $filePath): File
    {
        if (!defined('PHP_CODESNIFFER_CBF')) {
            define('PHP_CODESNIFFER_CBF', false);
        }

        $codeSniffer = new Runner();
        $codeSniffer->config = new Config(['-s']);
        $codeSniffer->config->stdinPath = $filePath;
        $codeSniffer->init();

        $sniffClassName = static::getSniffClassName();
        $sniff = new $sniffClassName();

        $codeSniffer->ruleset->sniffs = [$sniffClassName => $sniff];
        $codeSniffer->ruleset->populateTokenListeners();

        $fileContent = file_get_contents($filePath);
        self::assertNotFalse($fileContent);

        $file = new DummyFile($fileContent, $codeSniffer->ruleset, $codeSniffer->config);
        $file->process();

        return $file;
    }

    protected static function assertErrorCount(
        File $file,
        int $expectedErrorCount
    ): void
    {
        self::assertSame($expectedErrorCount, $file->getErrorCount());
    }

    protected static function assertNoErrorInFixedFile(File $phpcsFile): void
    {
        $phpcsFile->disableCaching();
        self::assertTrue($phpcsFile->fixer->fixFile());

        $actualContent = $phpcsFile->fixer->getContents();
        $expectedPath = $phpcsFile->getFilename() . '.fixed';

        if (!is_file($expectedPath) && getenv('CI') === false) {
            file_put_contents($expectedPath, $actualContent);
        }

        self::assertStringEqualsFile($expectedPath, $actualContent);
    }

    /**
     * @return class-string<Sniff>
     */
    protected static function getSniffClassName(): string
    {
        /** @var class-string<Sniff> $sniffClassName */
        $sniffClassName = str_replace(
            ['ShipMonkTests\\CodingStandard', 'SniffTest'],
            ['ShipMonkCodingStandard', 'Sniff'],
            static::class,
        );

        return $sniffClassName;
    }

}
