<?php

$x = match ($y) {
    default => 0
};

$z = match ($y) {
    1, 2, => 0,
    default => 1,
};
