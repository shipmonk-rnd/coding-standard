<?php declare(strict_types = 1);

// asymmetric-visibility tokens exist only on PHP >= 8.4 — polyfill so the parser's
// token-id lists load on older runtimes (the syntax itself then never tokenizes)
if (!defined('T_PUBLIC_SET')) {
    define('T_PUBLIC_SET', -3);
    define('T_PROTECTED_SET', -4);
    define('T_PRIVATE_SET', -5);
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'ShipMonkFmt\\';

    if (str_starts_with($class, $prefix)) {
        require __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
