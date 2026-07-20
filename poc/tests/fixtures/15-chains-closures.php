<?php

$result = $builder
    ->select('*')
    ->where('id', 5)
    ->fetch();

$flat = $obj->children()[0]->name;

$mapped = array_map(static fn (int $i): int => $i * 2, $items);

$each = array_filter($items, static function (int $i) use ($limit): bool {
    return $i < $limit;
});

$label = $count > 0
    ? 'some'
    : 'none';

$msg = new LogicException(sprintf('%s failed', $name));

$total = self::LIMIT + Config::DEFAULT->value;
