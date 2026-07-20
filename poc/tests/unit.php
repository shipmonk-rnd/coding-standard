<?php declare(strict_types = 1);

use ShipMonkFmt\FatalError;
use ShipMonkFmt\Verifier;

require __DIR__ . '/../bootstrap.php';

$pass = 0;
$fail = 0;

function ok(bool $cond, string $name): void
{
    global $pass, $fail;

    if ($cond) {
        $pass++;
        echo "PASS $name\n";
    } else {
        $fail++;
        echo "FAIL $name\n";
    }
}

function verifies(string $in, string $out): bool
{
    try {
        Verifier::verify($in, $out);

        return true;
    } catch (FatalError) {
        return false;
    }
}

// layout commas: may be added/removed before ) ] } and =>
ok(verifies('<?php foo(1, 2,);', '<?php foo(1, 2);'), 'comma removed before )');
ok(verifies('<?php $a = [1];', "<?php \$a = [\n    1,\n];"), 'comma added before ]');
ok(verifies('<?php $a = match ($x) { default => 0 };', "<?php \$a = match (\$x) {\n    default => 0,\n};"), 'comma added before }');
ok(verifies('<?php $a = match ($x) { 1, 2, => 0, default => 1 };', '<?php $a = match ($x) { 1, 2 => 0, default => 1 };'), 'comma removed before =>');

// non-layout token changes must be rejected
ok(!verifies('<?php foo(1, 2);', '<?php foo(1);'), 'dropped argument rejected');
ok(!verifies('<?php $a = 1;', '<?php $a = 2;'), 'changed literal rejected');
ok(!verifies('<?php foo(1, 2);', '<?php foo(1 2);'), 'dropped separator comma rejected');
ok(!verifies('<?php $a = 1;', '<?php $a = 1'), 'dropped semicolon rejected');
ok(!verifies('<?php $a = 1; // x', '<?php $a = 1;'), 'dropped comment rejected');

// comments compare modulo per-line leading whitespace only
ok(verifies("<?php\n/**\n     * x\n */\n\$a = 1;", "<?php\n/**\n * x\n */\n\$a = 1;"), 'docblock reindent accepted');
ok(!verifies("<?php\n/** a */\n\$a = 1;", "<?php\n/** b */\n\$a = 1;"), 'comment content change rejected');

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
