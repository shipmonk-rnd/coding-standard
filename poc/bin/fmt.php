<?php declare(strict_types = 1);

use ShipMonkFmt\Formatter;

require __DIR__ . '/../bootstrap.php';

$args = array_slice($argv, 1);
$check = in_array('--check', $args, true);
$write = in_array('--write', $args, true);
$files = array_values(array_filter($args, static fn (string $a): bool => !str_starts_with($a, '--')));

if ($files === []) {
    fwrite(STDERR, "usage: php bin/fmt.php [--check|--write] <file>...\n");
    exit(2);
}

$formatter = new Formatter();
$exit = 0;

foreach ($files as $file) {
    $source = file_get_contents($file);

    if ($source === false) {
        fwrite(STDERR, "$file: cannot read\n");
        $exit = 2;
        continue;
    }

    $result = $formatter->format($source);

    if ($result->fatal !== null) {
        fwrite(STDERR, "$file: FATAL: {$result->fatal}\n");
        $exit = 2;
        continue;
    }

    if ($check) {
        if ($result->changed || $result->violations !== []) {
            foreach ($result->violations as $violation) {
                echo "$file:{$violation->line}: {$violation->message}\n";
            }

            if ($result->changed && $result->violations === []) {
                echo "$file: needs formatting\n";
            }

            $exit = max($exit, 1);
        }
    } elseif ($write) {
        if ($result->changed) {
            file_put_contents($file, $result->output);
            echo "$file: fixed\n";
        }
    } else {
        echo $result->output;
    }
}

exit($exit);
