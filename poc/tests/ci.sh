#!/usr/bin/env bash
# Full CI gate for the PoC (notes/50 priority 6). Run from poc/:  tests/ci.sh
set -uo pipefail
cd "$(dirname "$0")/.."

fail=0
step() { echo; echo "=== $1"; }

step "lint"
for f in src/*.php bin/*.php tests/*.php bootstrap.php; do
    php -l "$f" > /dev/null || fail=1
done
echo "ok"

step "unit tests"
php tests/unit.php | tail -1 || fail=1

step "golden fixtures (+ idempotency)"
php tests/run.php | tail -1 || fail=1

step "shipmonk-standard corpus (must be 100% MATCH)"
php tests/corpus.php --expect-all-match \
    ../oss-doctrine-entity-preloader-wt-jt-fmt-corpus/src \
    ../oss-doctrine-entity-preloader-wt-jt-fmt-corpus/tests | tail -1 || fail=1

step "foreign corpora (safety invariants + fatal histogram)"
for dir in ../repos/PHP-CS-Fixer/src ../repos/PHP-Parser/lib ../repos/pretty-php/src ../repos/coding-standard; do
    [ -d "$dir" ] && { php tests/corpus.php "$dir" || fail=1; }
done

step "whitespace-perturbation fuzz"
php tests/fuzz.php ../oss-doctrine-entity-preloader-wt-jt-fmt-corpus/src ../oss-doctrine-entity-preloader-wt-jt-fmt-corpus/tests tests/fixtures || fail=1

step "differential parser oracle (informational)"
[ -d ../repos/PHP-Parser ] && php tests/oracle.php --parser=../repos/PHP-Parser ../repos/PHP-CS-Fixer/src | tail -1

echo
[ "$fail" -eq 0 ] && echo "CI: ALL GREEN" || echo "CI: FAILURES"
exit "$fail"
