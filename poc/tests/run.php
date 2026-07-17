<?php declare(strict_types = 1);

use ShipMonkFmt\Formatter;

require __DIR__ . '/../bootstrap.php';

/**
 * Golden-file harness (style kept from the existing standard, notes/01):
 *   X.php           input; expected UNCHANGED unless a companion exists
 *   X.php.fixed     expected output after formatting X.php
 *   X.php.fatal     expected substring of the fatal error for X.php
 * Every non-fatal case is additionally checked for idempotency:
 * formatting the expected output must return it byte-identical.
 */

function firstDiff(string $expected, string $actual): string
{
    $e = explode("\n", $expected);
    $a = explode("\n", $actual);
    $max = max(count($e), count($a));

    for ($i = 0; $i < $max; $i++) {
        $el = $e[$i] ?? '<missing>';
        $al = $a[$i] ?? '<missing>';

        if ($el !== $al) {
            return sprintf("    line %d\n    expected: %s\n    actual:   %s", $i + 1, var_export($el, true), var_export($al, true));
        }
    }

    return '    (no line diff — whitespace at EOF?)';
}

$formatter = new Formatter();
$pass = 0;
$fail = 0;

foreach (glob(__DIR__ . '/fixtures/*.php') as $file) {
    $name = basename($file);
    $input = file_get_contents($file);
    $result = $formatter->format($input);
    $errors = [];

    if (file_exists($file . '.violations')) {
        $expected = trim(file_get_contents($file . '.violations'));

        if ($result->fatal !== null) {
            $errors[] = "unexpected FATAL: {$result->fatal}";
        } elseif ($result->violations === []) {
            $errors[] = "expected a violation containing \"$expected\", got none";
        } else {
            $found = false;

            foreach ($result->violations as $violation) {
                if (str_contains($violation->message, $expected)) {
                    $found = true;
                }
            }

            if (!$found) {
                $errors[] = "no violation contains \"$expected\"";
            }
        }
    } elseif (file_exists($file . '.fatal')) {
        $expectedFatal = trim(file_get_contents($file . '.fatal'));

        if ($result->fatal === null) {
            $errors[] = "expected FATAL \"$expectedFatal\", got none";
        } elseif (!str_contains($result->fatal, $expectedFatal)) {
            $errors[] = "expected FATAL \"$expectedFatal\", got \"{$result->fatal}\"";
        }

        if ($result->output !== $input) {
            $errors[] = 'fatal must leave the input byte-identical';
        }
    } else {
        $expected = file_exists($file . '.fixed') ? file_get_contents($file . '.fixed') : $input;

        if ($result->fatal !== null) {
            $errors[] = "unexpected FATAL: {$result->fatal}";
        } elseif ($result->output !== $expected) {
            $errors[] = "output mismatch:\n" . firstDiff($expected, $result->output);
        } elseif ($result->changed !== ($expected !== $input)) {
            $errors[] = 'changed flag is wrong';
        }

        if ($errors === []) {
            $again = $formatter->format($expected);

            if ($again->fatal !== null) {
                $errors[] = "idempotency: FATAL on second run: {$again->fatal}";
            } elseif ($again->output !== $expected) {
                $errors[] = "NOT IDEMPOTENT:\n" . firstDiff($expected, $again->output);
            }
        }
    }

    if ($errors === []) {
        $pass++;
        echo "PASS $name\n";
    } else {
        $fail++;
        echo "FAIL $name\n";

        foreach ($errors as $error) {
            echo "  $error\n";
        }
    }
}

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
