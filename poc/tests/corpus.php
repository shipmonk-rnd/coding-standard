<?php declare(strict_types = 1);

use ShipMonkFmt\Formatter;

require __DIR__ . '/../bootstrap.php';

/**
 * Corpus runner (notes/50 §priorities): formats every .php file under the given
 * directories and enforces the safety invariants on each:
 *   - a FATAL result must leave the input byte-identical
 *   - a REPAIR result must be idempotent (formatting the output changes nothing)
 *   - verifier failures are counted separately and always fail the run
 * Prints a MATCH/REPAIR/FATAL summary + fatal-message histogram (the per-construct
 * coverage metric). --expect-all-match additionally fails unless every file MATCHes.
 */

$args = array_slice($argv, 1);
$expectAllMatch = in_array('--expect-all-match', $args, true);
$dirs = array_values(array_filter($args, static fn (string $a): bool => !str_starts_with($a, '--')));

if ($dirs === []) {
    fwrite(STDERR, "usage: php tests/corpus.php [--expect-all-match] <dir>...\n");
    exit(2);
}

function hasRecovery(ShipMonkFmt\FormatResult $result): bool
{
    foreach ($result->violations as $violation) {
        if (str_starts_with($violation->message, 'unsupported construct')) {
            return true;
        }
    }

    return false;
}

$formatter = new Formatter();
$match = $repair = $fatal = $verifyFailures = $safetyFailures = $recovered = 0;
$histogram = [];
$start = microtime(true);

foreach ($dirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        $result = $formatter->format($source);

        if ($result->fatal !== null) {
            $fatal++;

            if (str_contains($result->fatal, 'verification failed')) {
                $verifyFailures++;
                echo "VERIFY-FAIL {$file->getPathname()}: {$result->fatal}\n";
            }

            if ($result->output !== $source) {
                $safetyFailures++;
                echo "UNSAFE-FATAL {$file->getPathname()}\n";
            }

            $reason = preg_replace('~ on line \d+$~', '', preg_replace('~"[^"]*"~', '"…"', $result->fatal));
            $histogram[$reason] = ($histogram[$reason] ?? 0) + 1;
        } elseif (hasRecovery($result)) {
            // byte-identical output can still hide recovered (verbatim) statements —
            // count those files separately, never as MATCH
            $recovered++;

            foreach ($result->violations as $violation) {
                if (!str_starts_with($violation->message, 'unsupported construct')) {
                    continue;
                }

                $reason = preg_replace('~ on line \d+~', '', preg_replace('~"[^"]*"~', '"…"', $violation->message));
                $histogram[$reason] = ($histogram[$reason] ?? 0) + 1;
            }
        } elseif ($result->changed) {
            $repair++;
            $again = $formatter->format($result->output);

            if ($again->fatal !== null || $again->output !== $result->output) {
                $safetyFailures++;
                echo "NOT-IDEMPOTENT {$file->getPathname()}\n";
            }
        } else {
            $match++;
        }
    }
}

$total = $match + $repair + $fatal + $recovered;
printf(
    "files=%d match=%d repair=%d recovered=%d fatal=%d verify-failures=%d safety-failures=%d in %.1fs\n",
    $total,
    $match,
    $repair,
    $recovered,
    $fatal,
    $verifyFailures,
    $safetyFailures,
    microtime(true) - $start,
);
arsort($histogram);

foreach (array_slice($histogram, 0, 15, true) as $reason => $n) {
    printf("%5d  %s\n", $n, $reason);
}

if ($verifyFailures > 0 || $safetyFailures > 0) {
    exit(1);
}

if ($expectAllMatch && ($match !== $total || $recovered > 0)) {
    echo "FAIL: expected all files to MATCH\n";
    exit(1);
}

exit(0);
