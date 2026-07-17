<?php declare(strict_types = 1);

use ShipMonkFmt\Formatter;

require __DIR__ . '/../bootstrap.php';

/**
 * Differential parser oracle (notes/50 §5, CI-only, zero runtime coupling):
 * any file nikic/PHP-Parser can parse must not be a whole-file FATAL for us
 * (statement-level recovery should have degraded gracefully). Requires a
 * PHP-Parser checkout; pass its path as --parser=<dir> (autoloads lib/).
 */

$args = array_slice($argv, 1);
$parserDir = null;
$dirs = [];

foreach ($args as $arg) {
    if (str_starts_with($arg, '--parser=')) {
        $parserDir = substr($arg, strlen('--parser='));
    } else {
        $dirs[] = $arg;
    }
}

if ($parserDir === null || $dirs === []) {
    fwrite(STDERR, "usage: php tests/oracle.php --parser=<php-parser-checkout> <dir>...\n");
    exit(2);
}

spl_autoload_register(static function (string $class) use ($parserDir): void {
    if (str_starts_with($class, 'PhpParser\\')) {
        $path = $parserDir . '/lib/' . str_replace('\\', '/', $class) . '.php';

        if (file_exists($path)) {
            require $path;
        }
    }
});

$nikic = (new PhpParser\ParserFactory())->createForHostVersion();
$formatter = new Formatter();
$checked = $disagreements = 0;

foreach ($dirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        $result = $formatter->format($source);

        if ($result->fatal === null || str_starts_with($result->fatal, 'input is not valid PHP')) {
            continue;
        }

        try {
            $nikic->parse($source);
        } catch (PhpParser\Error) {
            continue; // nikic can't parse it either — not our gap
        }

        $checked++;
        $disagreements++;
        echo "ORACLE {$file->getPathname()}: {$result->fatal}\n";
    }
}

echo "oracle: whole-file fatals on nikic-parseable files: $disagreements\n";
exit(0); // informational — histogram feeds the roadmap, does not gate
