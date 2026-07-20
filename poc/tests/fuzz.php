<?php declare(strict_types = 1);

use ShipMonkFmt\Formatter;
use ShipMonkFmt\Lexer;
use ShipMonkFmt\SigToken;

require __DIR__ . '/../bootstrap.php';

/**
 * Whitespace-perturbation fuzz (notes/50 §5): for every corpus file that formats
 * cleanly (no fatal, no violations), randomize its HORIZONTAL whitespace — spacing
 * within lines and line indentation, never the line structure — and assert the
 * formatter converges to the same output. Targets exactly the repair paths and the
 * RenderCtx/indent logic.
 */

$args = array_slice($argv, 1);
$seed = 20260717;
$dirs = [];

foreach ($args as $arg) {
    if (str_starts_with($arg, '--seed=')) {
        $seed = (int) substr($arg, strlen('--seed='));
    } else {
        $dirs[] = $arg;
    }
}

if ($dirs === []) {
    fwrite(STDERR, "usage: php tests/fuzz.php [--seed=N] <dir>...\n");
    exit(2);
}

mt_srand($seed);

/**
 * Rebuild the source with perturbed horizontal whitespace. Only gaps are touched
 * (string/heredoc interiors carry their whitespace inside token text, not gaps),
 * and the newline structure of every gap is preserved exactly.
 */
function perturb(string $source): string
{
    $out = '';

    foreach (Lexer::tokenize($source) as $token) {
        $gap = $token->gapBefore;
        $lastNl = strrpos($gap, "\n");

        if ($lastNl === false) {
            if ($gap !== '') {
                $gap = str_repeat(' ', mt_rand(1, 4));
            }
        } else {
            $gap = substr($gap, 0, $lastNl + 1) . str_repeat(' ', mt_rand(0, 12));
        }

        $out .= $gap;

        if ($token->id === SigToken::EOF) {
            break;
        }

        $out .= $token->text;

        if ($token->trailingComment !== null) {
            $out .= str_repeat(' ', mt_rand(1, 3)) . $token->trailingComment->text;
        }
    }

    return $out;
}

$formatter = new Formatter();
$tested = $skipped = $failed = 0;

foreach ($dirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        $clean = $formatter->format($source);

        if ($clean->fatal !== null || $clean->violations !== []) {
            $skipped++; // verbatim-recovered statements legitimately keep their whitespace
            continue;
        }

        $perturbed = $formatter->format(perturb($source));
        $tested++;

        if ($perturbed->fatal !== null || $perturbed->output !== $clean->output) {
            $failed++;
            echo "FUZZ-FAIL {$file->getPathname()}" . ($perturbed->fatal !== null ? ": {$perturbed->fatal}" : '') . "\n";
        }
    }
}

echo "fuzz: tested=$tested skipped=$skipped failed=$failed (seed=$seed)\n";
exit($failed > 0 ? 1 : 0);
