<?php

declare(strict_types=1);

// A demonstração também funciona sem instalar o Composer.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Demo\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
