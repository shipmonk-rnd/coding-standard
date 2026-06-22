<?php declare(strict_types = 1);

try {
    foo();
} catch (
    AException |
    BException $e
    ) {
    bar();
}
